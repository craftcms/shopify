<?php

declare(strict_types=1);

namespace CraftCms\Shopify\BulkOperation;

use craft\helpers\Queue;
use craft\shopify\enums\BulkOperationStatus;
use craft\shopify\exceptions\ShopifyApiException;
use craft\shopify\jobs\ProcessBulkOperationData;
use craft\shopify\models\BulkOperation;
use CraftCms\Cms\Support\Query;
use CraftCms\Shopify\Api\Api;
use CraftCms\Shopify\BulkOperation\Models\BulkOperation as BulkOperationRecord;
use CraftCms\Shopify\Database\Table;
use GraphQL\InlineFragment;
use GraphQL\Mutation;
use GraphQL\Variable;
use Illuminate\Container\Attributes\Singleton;
use Illuminate\Contracts\Cache\Lock;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * BulkOperations service.
 *
 * This service is responsible for creating a local queue of “bulk” synchronization tasks, which are later dispatched to Shopify. The process looks something like this:
 *
 * 1. Most “bulk” operations are created in response to webhooks, but a user may also request a full synchronization via the Utility or CLI.
 * 2. A local record is created to track pending synchronizations
 * 3. The plugin checks to see if Shopify is currently processing another bulk operation. If not, we push the query to the API.
 * 4. When a bulk query finishes running on Shopify’s infrastructure, they issue a webhook.
 * 5. In response to the webhook, we store the URL to the operation’s JSONL results and push a {@see ProcessBulkOperationData} to the Craft queue. That job is responsible for actually updating our local {@see craft\shopify\elements\Product} records.
 * 6. After processing a bulk operation, we return to step #3.
 *
 * The system goes “idle” if there are no operations in our queue, or after adding an operation to the local queue while Shopfiy is still processing a prior one.
 *
 * Complete, failed, or otherwise “terminal” bulk operations can be deleted from the queue.
 *
 * @link https://shopify.dev/docs/api/usage/bulk-operations/queries
 */
#[Singleton]
class BulkOperations
{
    /**
     * Name of the mutex lock guarding the check-then-act status transitions in {@see nextBulkOperation()}
     * and {@see queueNextBulkOperation()}. Both methods share one lock, since they read and write
     * overlapping bulk operation statuses and must exclude one another, not just themselves.
     *
     * @since 8.1.0
     */
    public const string MUTEX_NAME = 'shopify-bulk-operation';

    /**
     * How long, in seconds, the lock is held before it expires, in case a process dies while holding it.
     */
    private const int MUTEX_TTL = 300;

    public function __construct(
        private readonly Api $api,
    ) {
    }

    /**
     * @return Collection
     */
    public function getAllBulkOperations(): Collection
    {
        return $this->createBulkOperationQuery()
            ->get()
            ->map(fn(object $row) => new BulkOperation((array)$row));
    }

    /**
     * @param string $gid
     * @return BulkOperation|null
     * @since 8.0.0
     */
    public function getBulkOperationByShopifyGid(string $gid): ?BulkOperation
    {
        return $this->getAllBulkOperations()->firstWhere('shopifyGid', $gid);
    }

    /**
     * @param string $shopifyId
     * @return BulkOperation|null
     * @deprecated in 8.0.0. Use [[getBulkOperationByShopifyGid()]] instead.
     */
    public function getBulkOperationByShopifyId(string $shopifyId): ?BulkOperation
    {
        return $this->getBulkOperationByShopifyGid($shopifyId);
    }

    /**
     * Queues a bulk operation, unless an identical one is already waiting in the local queue.
     *
     * A queued operation fetches the latest data when it is sent, so a second one would be redundant.
     *
     * @param string $query
     * @param string $clearData
     * @return bool
     */
    public function createBulkOperation(string $query, string $clearData = BulkOperationRecord::CLEAR_DATA_NONE): bool
    {
        $bulkOperation = new BulkOperation([
            'status' => BulkOperationStatus::Queued,
            'query' => $query,
            'clearData' => $clearData,
        ]);

        if (!$this->saveQueuedBulkOperation($bulkOperation)) {
            Log::error('Could not save bulk operation data.');
            return false;
        }

        $this->nextBulkOperation();
        return true;
    }

    /**
     * Deletes the bulk operations for a Shopify object that are still waiting in the local queue.
     *
     * Operations that have been sent to Shopify or are being processed are left alone.
     *
     * @param string $gid The Shopify GID of the object the operations were queued for.
     * @return bool Whether the operations were deleted. Returns false if the queue is busy.
     * @since 8.3.0
     */
    public function deleteQueuedBulkOperationsByShopifyGid(string $gid): bool
    {
        // Operations are claimed under this lock, so wait for any in-flight send rather than deleting a row that is about to be dispatched.
        // Keep the wait short, as this runs in a webhook request and Shopify times those out after five seconds.
        if (!$lock = $this->acquireLock(1)) {
            return false;
        }

        try {
            DB::table(Table::BULK_OPERATIONS)
                ->where('status', BulkOperationStatus::Queued->value)
                ->where('clearData', $gid)
                ->delete();
        } finally {
            $lock->release();
        }

        return true;
    }

    /**
     * Retrieve all a shop’s products.
     *
     * @return bool
     */
    public function createProductsBulkOperation(): bool
    {
        return $this->createBulkOperation((string)$this->api->getProductGql(), BulkOperationRecord::CLEAR_DATA_ALL);
    }

    /**
     * @return bool
     */
    public function nextBulkOperation(): bool
    {
        // Wait briefly for another process to finish deciding whether to start a bulk op, rather than
        // bailing immediately—most contention here is just two webhooks landing at nearly the same
        // moment, and the holder typically releases well within a second.
        if (!$lock = $this->acquireLock(1)) {
            return false;
        }

        try {
            // Fail anything that's been stuck since before we got the lock, so it doesn't block this
            // call—without this, an abandoned row would sit here until Craft's GC happens to run.
            $this->failStuckBulkOperations();

            // If we are processing the data of a bulk op or a bulk op query has been sent to Shopify, we can't start another one
            $hasBulkOpsInProgress = $this->createBulkOperationQuery()
                ->whereIn('status', [BulkOperationStatus::Processing->value, BulkOperationStatus::Created->value])
                ->exists();

            if ($hasBulkOpsInProgress) {
                return false;
            }

            // Retrieve the next queued bulk operation, oldest first, so a backlog drains in order
            // rather than letting new work continually jump ahead of it.
            $result = $this->createBulkOperationQuery()
                ->where('status', BulkOperationStatus::Queued->value)
                ->reorder('id')
                ->first();

            if (!$result) {
                // We don’t have any queued bulk operations, locally
                return true;
            }

            $bulkOperation = new BulkOperation((array)$result);

            // As of `2026-01` it is possible to have multiple bulk operations running concurrently,
            // but we should still check the API before trying to start another one.
            $bulkOpsStatusQuery = (new \GraphQL\Query('bulkOperations'))
                ->setOperationName('bulkOperations')
                ->setArguments([
                    'first' => 1,
                    'query' => 'status:running OR created',
                ])
                ->setSelectionSet([
                    (new \GraphQL\Query('edges'))
                        ->setSelectionSet([
                            (new \GraphQL\Query('node'))
                                ->setSelectionSet([
                                    'id',
                                    'status',
                                    'type',
                                ]),
                        ]),
                ]);

            try {
                $bulkOpStatusResponse = $this->api->query($bulkOpsStatusQuery);
            } catch (ShopifyApiException $e) {
                return false;
            }

            // If there is a bulk operation in progress, we should bail before trying to start another:
            $shopifyBulkOpStatus = $bulkOpStatusResponse['edges'][0]['node']['status'] ?? null;
            if ($shopifyBulkOpStatus) {
                return false;
            }

            $mutation = (new Mutation('bulkOperationRunQuery'))
                ->setOperationName('bulkOperationRunQuery')
                ->setVariables([new Variable('query', 'String!')])
                ->setArguments(['query' => '$query'])
                ->setSelectionSet([
                    (new \GraphQL\Query('bulkOperation'))
                        ->setSelectionSet([
                            'id',
                            'status',
                            'type',
                        ]),
                    (new \GraphQL\Query('userErrors'))
                        ->setSelectionSet([
                            'code',
                            'field',
                            'message',
                        ]),
                ]);

            try {
                $data = $this->api->query($mutation, ['query' => $bulkOperation->query]);
            } catch (ShopifyApiException $e) {
                // If there was an issue creating the operation that we haven’t accounted for, just mark it as completed:
                Log::error('Could not start bulk operation: ' . $e->getMessage());

                $bulkOperation->setStatus(BulkOperationStatus::Completed);
                $this->saveBulkOperation($bulkOperation, false);

                return false;
            }

            $op = $data['bulkOperation'];

            if ($op['status'] === 'CREATED') {
                $bulkOperation->setStatus(BulkOperationStatus::Created);
            }

            $bulkOperation->shopifyStatus = $op['status'];
            $bulkOperation->shopifyGid = $op['id'];
            $this->saveBulkOperation($bulkOperation);

            return true;
        } finally {
            $lock->release();
        }
    }

    /**
     * Processes an incoming bulk operation webhook.
     *
     * The only topic that Shopify supports is {@see Topics::BULK_OPERATIONS_FINISH}, which covers both successes and failures.
     * This method runs synchronously, *during the webhook delivery*, which means it should return as early as possible.
     *
     * When we receive a webhook indicating that a bulk operation has finished successfully, the next operation should be queued.
     *
     * @param array $payload
     * @return void
     * @since 6.0.0
     */
    public function handleBulkOperationFinished(array $payload): void
    {
        // An API ID must be present in order to do anything:
        if (!isset($payload['admin_graphql_api_id'])) {
            return;
        }

        // Load our local record of the bulk op:
        $bulkOperation = $this->getBulkOperationByShopifyGid($payload['admin_graphql_api_id']);

        if (!$bulkOperation) {
            // Ok... maybe it was initiated for a different environment?
            return;
        }

        // Load the complete bulk operation object from the API:
        // (The schema of the webhook payload and the actual object are different in subtle ways—like the casing of statuses.)
        $query = $this->createBulkOperationGqlQuery()
            ->setArguments(['id' => $payload['admin_graphql_api_id']]);

        try {
            $apiObject = $this->api->query($query);
        } catch (\Exception $e) {
            Log::error('Could not get bulk operation data: ' . $e->getMessage());
            return;
        }

        Log::info(sprintf('Shopify bulk data op finished with status %s! (Error code: %s)', $apiObject['status'], $apiObject['errorCode'] ?? 'none'));

        // If it isn't “completed,” the only action we need to take is updating our record:
        if ($apiObject['status'] !== 'COMPLETED') {
            $terminalStatuses = [
                'CANCELED',
                'EXPIRED',
                'FAILED',
            ];

            // Our similarly-named “complete” status is mostly an indication about whether we expect further activity from the operation.
            // Moving it out of the “processing” status also allows a user to delete/purge it from the sync history. {@see \CraftCms\Shopify\Http\Controllers\SyncController::destroy()}
            if (in_array($apiObject['status'], $terminalStatuses)) {
                $bulkOperation->setStatus(BulkOperationStatus::Completed);
            }

            // Save the “real” Shopify status, whatever it was:
            $bulkOperation->shopifyStatus = $apiObject['status'];
            $this->saveBulkOperation($bulkOperation, false);

            return;
        }

        // At this point, we know the status is `COMPLETED` and it’s safe to process!
        // Store the new status, and a reference to the external JSONL file:
        $bulkOperation->shopifyStatus = $apiObject['status'];
        $bulkOperation->url = $apiObject['url'];
        $bulkOperation->objectCount = (int)$apiObject['objectCount'];

        // Shopify omits the URL when the query matched nothing, so there is no data to process.
        // Finish the operation here, because nothing else will move it out of the “created” status.
        if (!$bulkOperation->url) {
            $bulkOperation->setStatus(BulkOperationStatus::Completed);
            $this->saveBulkOperation($bulkOperation, false);
            $this->nextBulkOperation();

            return;
        }

        if (!$this->saveBulkOperation($bulkOperation)) {
            Log::error('Could not save bulk operation data.');
        }

        $this->queueNextBulkOperation();
    }

    /**
     * @return bool
     */
    public function queueNextBulkOperation(): bool
    {
        // Wait briefly for another process to finish claiming a bulk op, rather than bailing
        // immediately—see the matching comment in nextBulkOperation().
        if (!$lock = $this->acquireLock(1)) {
            return false;
        }

        try {
            // See the matching comment in nextBulkOperation().
            $this->failStuckBulkOperations();

            $hasBulkOpsInProgress = $this->createBulkOperationQuery()
                ->where('status', BulkOperationStatus::Processing->value)
                ->exists();

            if ($hasBulkOpsInProgress) {
                return false;
            }

            // Oldest first, same reasoning as nextBulkOperation() above.
            $nextToProcess = $this->createBulkOperationQuery()
                ->where('status', BulkOperationStatus::Created->value)
                ->whereNotNull('url')
                ->reorder('id')
                ->first();

            if (!$nextToProcess) {
                return true;
            }

            $bulkOperation = new BulkOperation((array)$nextToProcess);
            Queue::push(new ProcessBulkOperationData([
                'bulkOperationShopifyGid' => $bulkOperation->shopifyGid,
                'dataUrl' => $bulkOperation->url,
                'objectCount' => $bulkOperation->objectCount,
                'clearData' => $bulkOperation->clearData,
            ]));

            $bulkOperation->setStatus(BulkOperationStatus::Processing);

            if (!$this->saveBulkOperation($bulkOperation)) {
                Log::error('Could not save bulk operation data.');
                return false;
            }

            return true;
        } finally {
            $lock->release();
        }
    }

    /**
     * @param BulkOperation $bulkOperation
     * @param bool $runValidation
     * @return bool
     */
    public function saveBulkOperation(BulkOperation $bulkOperation, bool $runValidation = true): bool
    {
        // Find record if it exists
        if ($bulkOperation->id) {
            $record = BulkOperationRecord::query()->find($bulkOperation->id);
        } elseif ($bulkOperation->shopifyGid) {
            $record = BulkOperationRecord::query()->where('shopifyGid', $bulkOperation->shopifyGid)->first();
        } else {
            // No `id` and no `shopifyGid` yet (e.g. a newly-created, not-yet-dispatched operation).
            $record = null;
        }

        if (!$record) {
            $record = new BulkOperationRecord();
        }

        if ($runValidation && !$bulkOperation->validate()) {
            Log::info('Bulk operation not saved due to validation error.');

            return false;
        }

        $record->clearData = $bulkOperation->clearData;
        $record->shopifyGid = $bulkOperation->shopifyGid;
        $record->url = $bulkOperation->url;
        $record->objectCount = $bulkOperation->objectCount;
        $record->query = $bulkOperation->query;
        $record->status = $bulkOperation->getStatus()?->value;
        $record->shopifyStatus = $bulkOperation->shopifyStatus;

        if (!$record->save()) {
            return false;
        }

        $bulkOperation->id = $record->id;

        return true;
    }

    /**
     * @param int $id
     * @return bool
     * @throws \Throwable
     */
    public function deleteBulkOperationById(int $id): bool
    {
        $bulkOperation = BulkOperationRecord::query()->find($id);
        if (!$bulkOperation) {
            return true;
        }

        // Cannot delete a bulk operation that is currently processing
        if ($bulkOperation->status === BulkOperationStatus::Processing->value) {
            return false;
        }

        if (!$bulkOperation->delete()) {
            return false;
        }

        return true;
    }

    /**
     * @return void
     * @throws \Throwable
     */
    public function purgeBulkOperations(): void
    {
        $this->failStuckBulkOperations();

        // Delete all bulk operations that reached a terminal state more than 7 days ago
        $terminalBulkOpIds = $this->createBulkOperationQuery()
            ->whereIn('status', [BulkOperationStatus::Completed->value, BulkOperationStatus::Failed->value])
            ->where('dateUpdated', '<', Query::prepareDateForDb(now()->subDays(7)))
            ->pluck('id');

        foreach ($terminalBulkOpIds as $id) {
            $this->deleteBulkOperationById($id);
        }
    }

    /**
     * Marks bulk operations stuck in `created`/`processing` for more than 24 hours as `failed`, so
     * they stop blocking {@see nextBulkOperation()} and {@see queueNextBulkOperation()}.
     */
    private function failStuckBulkOperations(): void
    {
        $stuckBulkOps = $this->createBulkOperationQuery()
            ->whereIn('status', [BulkOperationStatus::Created->value, BulkOperationStatus::Processing->value])
            ->where('dateUpdated', '<', Query::prepareDateForDb(now()->subHours(24)))
            ->get();

        foreach ($stuckBulkOps as $stuckBulkOp) {
            $bulkOperation = new BulkOperation((array)$stuckBulkOp);
            $bulkOperation->setStatus(BulkOperationStatus::Failed);
            $this->saveBulkOperation($bulkOperation, false);
        }
    }

    /**
     * Saves a newly queued bulk operation, skipping it when an identical one is already queued.
     *
     * The check and the save happen under the lock shared with {@see nextBulkOperation()}, so concurrent
     * callers can’t both miss each other’s row, and an identical row that is mid-send isn’t mistaken for
     * a queued one. If the lock can’t be acquired the operation is saved anyway; a duplicate is harmless,
     * a dropped sync is not.
     */
    private function saveQueuedBulkOperation(BulkOperation $bulkOperation): bool
    {
        // Don’t wait: this runs in a webhook request, and a busy lock only means we save a harmless duplicate
        if (!$lock = $this->acquireLock(0)) {
            return $this->saveBulkOperation($bulkOperation);
        }

        try {
            $hasQueued = $this->createBulkOperationQuery()
                ->where('status', BulkOperationStatus::Queued->value)
                ->where('query', $bulkOperation->query)
                ->where('clearData', $bulkOperation->clearData)
                ->exists();

            return $hasQueued || $this->saveBulkOperation($bulkOperation);
        } finally {
            $lock->release();
        }
    }

    /**
     * Acquires the lock shared by the methods that change bulk operation statuses, waiting up to `$wait` seconds.
     */
    private function acquireLock(int $wait): ?Lock
    {
        $lock = Cache::lock(self::MUTEX_NAME, self::MUTEX_TTL);

        if ($wait === 0) {
            return $lock->get() ? $lock : null;
        }

        try {
            $lock->block($wait);
        } catch (LockTimeoutException) {
            return null;
        }

        return $lock;
    }

    /**
     * Returns a query for bulk operation rows, newest first.
     */
    private function createBulkOperationQuery(): Builder
    {
        return DB::table(Table::BULK_OPERATIONS)
            ->select([
                'clearData',
                'dateCreated',
                'dateUpdated',
                'id',
                'objectCount',
                'query',
                'shopifyGid',
                'status',
                'shopifyStatus',
                'url',
            ])
            ->orderByDesc('id');
    }

    /**
     * Creates a GQL query for retrieving BulkOperation objects.
     */
    private function createBulkOperationGqlQuery(): \GraphQL\Query
    {
        return (new \GraphQL\Query('node'))
            ->setSelectionSet([
                (new InlineFragment('BulkOperation'))
                    ->setSelectionSet([
                        'status',
                        'errorCode',
                        'url',
                        'partialDataUrl',
                        'objectCount',
                    ]),
            ]);
    }
}
