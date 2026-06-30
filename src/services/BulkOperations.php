<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\shopify\services;

use Craft;
use craft\base\Component;
use craft\db\Query;
use craft\helpers\DateTimeHelper;
use craft\helpers\Db;
use craft\helpers\Queue;
use craft\shopify\db\Table;
use craft\shopify\enums\BulkOperationStatus;
use craft\shopify\jobs\ProcessBulkOperationData;
use craft\shopify\models\BulkOperation;
use craft\shopify\Plugin;
use craft\shopify\records\BulkOperation as BulkOperationRecord;
use GraphQL\InlineFragment;
use GraphQL\Mutation;
use GraphQL\Variable;
use Illuminate\Support\Collection;
use Shopify\Exception\ShopifyException;
use yii\base\InvalidConfigException;
use yii\db\Exception;
use yii\db\StaleObjectException;

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
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 6.0.0
 */
class BulkOperations extends Component
{
    /**
     * @return Collection
     * @throws InvalidConfigException
     */
    public function getAllBulkOperations(): Collection
    {
        $bulkOps = $this->_createBulkOperationQuery()->all();

        foreach ($bulkOps as &$bulkOp) {
            $bulkOp = Craft::createObject(array_merge($bulkOp, ['class' => BulkOperation::class]));
        }

        return collect($bulkOps);
    }

    /**
     * @param string $gid
     * @return BulkOperation|null
     * @throws InvalidConfigException
     * @since 8.0.0
     */
    public function getBulkOperationByShopifyGid(string $gid): ?BulkOperation
    {
        return $this->getAllBulkOperations()->firstWhere('shopifyGid', $gid);
    }

    /**
     * @param string $shopifyId
     * @return BulkOperation|null
     * @throws InvalidConfigException
     * @deprecated in 8.0.0. Use [[getBulkOperationByShopifyGid()]] instead.
     */
    public function getBulkOperationByShopifyId(string $shopifyId): ?BulkOperation
    {
        return $this->getBulkOperationByShopifyGid($shopifyId);
    }

    /**
     * @param string $query
     * @return bool
     * @throws Exception
     * @throws InvalidConfigException
     */
    public function createBulkOperation(string $query, string $clearData = BulkOperationRecord::CLEAR_DATA_NONE): bool
    {
        $bulkOperation = Craft::createObject([
            'class' => BulkOperation::class,
            'status' => BulkOperationStatus::Queued,
            'query' => $query,
            'clearData' => $clearData,
        ]);

        if (!$this->saveBulkOperation($bulkOperation)) {
            Craft::error('Could not save bulk operation data.', __METHOD__);
            return false;
        }

        $this->nextBulkOperation();
        return true;
    }

    /**
     * Retrieve all a shop’s products.
     *
     * @return bool
     * @throws Exception
     * @throws InvalidConfigException
     */
    public function createProductsBulkOperation(): bool
    {
        return $this->createBulkOperation((string)Plugin::getInstance()->getApi()->getProductGql(), BulkOperationRecord::CLEAR_DATA_ALL);
    }

    /**
     * @return bool
     * @throws InvalidConfigException
     */
    public function nextBulkOperation(): bool
    {
        // If we are processing the data of a bulk op or a bulk op query has been sent to Shopify, we can't start another one
        $hasBulkOpsInProgress = $this->_createBulkOperationQuery()
            ->andWhere([
                'status' => [BulkOperationStatus::Processing->value, BulkOperationStatus::Created->value],
            ])
            ->exists();

        if ($hasBulkOpsInProgress) {
            return false;
        }

        // Retrieve the next queued bulk operation
        $result = $this->_createBulkOperationQuery()
            ->andWhere(['status' => BulkOperationStatus::Queued->value])
            ->one();

        if (!$result) {
            // We don’t have any queued bulk operations, locally
            return true;
        }

        /** @var BulkOperation $bulkOperation */
        $bulkOperation = Craft::createObject(array_merge($result, ['class' => BulkOperation::class]));

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
            $bulkOpStatusResponse = Plugin::getInstance()->getApi()->query($bulkOpsStatusQuery);
        } catch (ShopifyException $e) {
            return false;
        }

        // If there is a bulk operation in progress, we should bail before trying to start another:
        if ($bulkOpStatusResponse && !empty($bulkOpStatusResponse['status'])) {
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
            $data = Plugin::getInstance()->getApi()->query($mutation, ['query' => $bulkOperation->query]);
        } catch (ShopifyException $e) {
            // If there was an issue creating the operation that we haven’t accounted for, just mark it as completed:
            Craft::error('Could not start bulk operation: ' . $e->getMessage(), __METHOD__);

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
        $query = $this->_createBulkOperationGqlQuery()
            ->setArguments(['id' => $payload['admin_graphql_api_id']]);

        try {
            $apiObject = Plugin::getInstance()->getApi()->query($query);
        } catch (\Exception $e) {
            Craft::error('Could not get bulk operation data: ' . $e->getMessage(), __METHOD__);
            return;
        }

        Craft::info(sprintf('Shopify bulk data op finished with status %s! (Error code: %s)', $apiObject['status'], $apiObject['errorCode'] ?? 'none'));

        // If it isn't “completed,” the only action we need to take is updating our record:
        if ($apiObject['status'] !== 'COMPLETED') {
            $terminalStatuses = [
                'CANCELED',
                'EXPIRED',
                'FAILED',
            ];

            // Our similarly-named “complete” status is mostly an indication about whether we expect further activity from the operation.
            // Moving it out of the “processing” status also allows a user to delete/purge it from the sync history. {@see craft\shopify\controllers\SyncController::actionDelete()}
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
        $bulkOperation->objectCount = $apiObject['objectCount'];

        if (!$this->saveBulkOperation($bulkOperation)) {
            Craft::error('Could not save bulk operation data.', __METHOD__);
        }

        $this->queueNextBulkOperation();
    }

    /**
     * @return bool
     * @throws Exception
     * @throws InvalidConfigException
     */
    public function queueNextBulkOperation(): bool
    {
        $hasBulkOpsInProgress = $this->_createBulkOperationQuery()
            ->andWhere([
                'status' => [BulkOperationStatus::Processing->value],
            ])
            ->exists();

        if ($hasBulkOpsInProgress) {
            return false;
        }

        $nextToProcess = $this->_createBulkOperationQuery()
            ->andWhere(['status' => BulkOperationStatus::Created->value])
            ->andWhere(['not', ['url' => null]])
            ->one();

        if (!$nextToProcess) {
            return true;
        }

        /** @var BulkOperation $bulkOperation */
        $bulkOperation = Craft::createObject(array_merge($nextToProcess, ['class' => BulkOperation::class]));
        if (!Queue::push(new ProcessBulkOperationData([
            'bulkOperationShopifyGid' => $bulkOperation->shopifyGid,
            'dataUrl' => $bulkOperation->url,
            'objectCount' => $bulkOperation->objectCount,
            'clearData' => $bulkOperation->clearData,
        ]))) {
            return false;
        }

        $bulkOperation->setStatus(BulkOperationStatus::Processing);

        if (!$this->saveBulkOperation($bulkOperation)) {
            Craft::error('Could not save bulk operation data.', __METHOD__);
            return false;
        }

        return true;
    }

    /**
     * @param BulkOperation $bulkOperation
     * @param bool $runValidation
     * @return bool
     * @throws Exception
     */
    public function saveBulkOperation(BulkOperation $bulkOperation, bool $runValidation = true): bool
    {
        // Find record if it exists
        if ($bulkOperation->id) {
            $record = BulkOperationRecord::findOne($bulkOperation->id);
        } else {
            $record = BulkOperationRecord::findOne(['shopifyGid' => $bulkOperation->shopifyGid]);
        }

        if (!$record) {
            $record = new BulkOperationRecord();
        }

        if ($runValidation && !$bulkOperation->validate()) {
            Craft::info('Bulk operation not saved due to validation error.', __METHOD__);

            return false;
        }

        $record->clearData = $bulkOperation->clearData;
        $record->shopifyGid = $bulkOperation->shopifyGid;
        $record->url = $bulkOperation->url;
        $record->objectCount = $bulkOperation->objectCount;
        $record->query = $bulkOperation->query;
        $record->status = $bulkOperation->getStatus()?->value;
        $record->shopifyStatus = $bulkOperation->shopifyStatus;

        $record->validate();
        $bulkOperation->addErrors($record->getErrors());

        if (!$record->save(false)) {
            return false;
        }

        $bulkOperation->id = $record->id;

        return true;
    }

    /**
     * @param int $id
     * @return bool
     * @throws \Throwable
     * @throws \yii\db\StaleObjectException
     */
    public function deleteBulkOperationById(int $id): bool
    {
        $bulkOperation = BulkOperationRecord::findOne(['id' => $id]);
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
     * @throws \DateInvalidOperationException
     * @throws \Throwable
     * @throws StaleObjectException
     */
    public function purgeBulkOperations(): void
    {
        $edge = DateTimeHelper::now();
        $interval = DateTimeHelper::toDateInterval('P7D');
        $edge->sub($interval);

        // Delete all bulk operations completed and older than 7 days
        $completedBulkOps = $this->_createBulkOperationQuery()
            ->andWhere(['status' => BulkOperationStatus::Completed->value])
            ->andWhere(['<', 'dateUpdated', Db::prepareDateForDb($edge)])
            ->all();

        foreach ($completedBulkOps as $completedBulkOp) {
            $this->deleteBulkOperationById($completedBulkOp['id']);
        }
    }

    /**
     * Returns a Query object prepped for retrieving shipping methods.
     */
    private function _createBulkOperationQuery(): Query
    {
        return (new Query())
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
            ->from([Table::BULK_OPERATIONS])
            ->orderBy(['id' => SORT_DESC]);
    }

    /**
     * Creates a GQL query for retrieving BulkOperation objects.
     */
    private function _createBulkOperationGqlQuery(): \GraphQL\Query
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
