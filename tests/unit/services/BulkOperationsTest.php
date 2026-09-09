<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\shopify\tests\unit\services;

use Codeception\Test\Unit;
use Craft;
use craft\mutex\Mutex;
use craft\shopify\db\Table;
use craft\shopify\enums\BulkOperationStatus;
use craft\shopify\models\BulkOperation;
use craft\shopify\Plugin;
use craft\shopify\services\Api;
use craft\shopify\services\BulkOperations;
use craft\shopify\tests\fixtures\BulkOperationsFixture;
use UnitTester;

/**
 * @group services
 */
class BulkOperationsTest extends Unit
{
    public UnitTester $tester;

    // Shopify GID from the fixture that we can reference in tests
    private const FIXTURE_GID = 'gid://shopify/BulkOperation/4848685842483';

    public function _fixtures(): array
    {
        return [
            'bulkOperations' => ['class' => BulkOperationsFixture::class],
        ];
    }

    protected function _after(): void
    {
        // Restore the real Api service after any test that swaps it out
        Plugin::getInstance()->set('api', Api::class);

        // Restore the real mutex component after any test that swaps it out
        Craft::$app->set('mutex', Mutex::class);
    }

    // -------------------------------------------------------------------------
    // getAllBulkOperations
    // -------------------------------------------------------------------------

    public function testGetAllBulkOperationsReturnsCollection(): void
    {
        $result = Plugin::getInstance()->getBulkOperations()->getAllBulkOperations();

        self::assertInstanceOf(\Illuminate\Support\Collection::class, $result);
        self::assertGreaterThanOrEqual(5, $result->count()); // 5 loaded from fixture
        self::assertContainsOnlyInstancesOf(BulkOperation::class, $result->all());
    }

    public function testGetAllBulkOperationsContainsFixtureData(): void
    {
        $ops = Plugin::getInstance()->getBulkOperations()->getAllBulkOperations();
        $shopifyIds = $ops->pluck('shopifyId')->all();

        self::assertContains(self::FIXTURE_GID, $shopifyIds);
    }

    // -------------------------------------------------------------------------
    // getBulkOperationByShopifyId
    // -------------------------------------------------------------------------

    public function testGetBulkOperationByShopifyIdFindsKnownRecord(): void
    {
        $op = Plugin::getInstance()->getBulkOperations()->getBulkOperationByShopifyId(self::FIXTURE_GID);

        self::assertNotNull($op);
        self::assertInstanceOf(BulkOperation::class, $op);
        self::assertEquals(self::FIXTURE_GID, $op->shopifyId);
        self::assertEquals(BulkOperationStatus::Completed, $op->getStatus());
    }

    public function testGetBulkOperationByShopifyIdReturnsNullForUnknownId(): void
    {
        $op = Plugin::getInstance()->getBulkOperations()->getBulkOperationByShopifyId('gid://shopify/BulkOperation/does-not-exist');

        self::assertNull($op);
    }

    // -------------------------------------------------------------------------
    // saveBulkOperation
    // -------------------------------------------------------------------------

    public function testSaveBulkOperationCreatesNewRecord(): void
    {
        $model = $this->_makeQueuedOp('gid://shopify/BulkOperation/new-test-999');

        $result = Plugin::getInstance()->getBulkOperations()->saveBulkOperation($model, false);

        self::assertTrue($result);
        self::assertNotNull($model->id);
    }

    public function testSaveBulkOperationUpdatesExistingRecord(): void
    {
        $service = Plugin::getInstance()->getBulkOperations();
        $op = $service->getBulkOperationByShopifyId(self::FIXTURE_GID);

        $op->objectCount = 9999;
        $service->saveBulkOperation($op, false);

        $reloaded = $service->getBulkOperationByShopifyId(self::FIXTURE_GID);
        self::assertEquals(9999, $reloaded->objectCount);
    }

    /**
     * Regression test for craftcms/shopify#224: two not-yet-dispatched operations both have a
     * null `id` and a null `shopifyId`. Before the fix, the second save would match the first
     * row via `WHERE shopifyId IS NULL` and silently overwrite it instead of inserting a new row.
     */
    public function testSaveBulkOperationDoesNotCollideWhenShopifyIdIsNull(): void
    {
        $service = Plugin::getInstance()->getBulkOperations();

        $first = $this->_makeFreshQueuedOp('query { first }');
        $service->saveBulkOperation($first, false);

        $second = $this->_makeFreshQueuedOp('query { second }');
        $service->saveBulkOperation($second, false);

        self::assertNotNull($first->id);
        self::assertNotNull($second->id);
        self::assertNotEquals($first->id, $second->id, 'A second not-yet-dispatched operation should not overwrite the first.');

        $all = $service->getAllBulkOperations();
        $reloadedFirst = $all->firstWhere('id', $first->id);
        $reloadedSecond = $all->firstWhere('id', $second->id);

        self::assertNotNull($reloadedFirst);
        self::assertNotNull($reloadedSecond);
        self::assertEquals('query { first }', $reloadedFirst->query);
        self::assertEquals('query { second }', $reloadedSecond->query);
    }

    // -------------------------------------------------------------------------
    // deleteBulkOperationById
    // -------------------------------------------------------------------------

    public function testDeleteBulkOperationByIdRemovesRecord(): void
    {
        $service = Plugin::getInstance()->getBulkOperations();
        $model = $this->_makeQueuedOp('gid://shopify/BulkOperation/delete-test-001');
        $service->saveBulkOperation($model, false);
        $id = $model->id;

        $result = $service->deleteBulkOperationById($id);

        self::assertTrue($result);
        self::assertNull($service->getBulkOperationByShopifyId('gid://shopify/BulkOperation/delete-test-001'));
    }

    public function testDeleteBulkOperationByIdReturnsTrueForMissingRecord(): void
    {
        $result = Plugin::getInstance()->getBulkOperations()->deleteBulkOperationById(999999);
        self::assertTrue($result);
    }

    public function testCannotDeleteProcessingBulkOperation(): void
    {
        $service = Plugin::getInstance()->getBulkOperations();
        $model = $this->_makeQueuedOp('gid://shopify/BulkOperation/processing-test-001');
        $model->setStatus(BulkOperationStatus::Processing);
        $service->saveBulkOperation($model, false);

        $result = $service->deleteBulkOperationById($model->id);

        self::assertFalse($result);
        self::assertNotNull($service->getBulkOperationByShopifyId('gid://shopify/BulkOperation/processing-test-001'));
    }

    public function testFailedBulkOperationCanBeDeleted(): void
    {
        $service = Plugin::getInstance()->getBulkOperations();
        $model = $this->_makeQueuedOp('gid://shopify/BulkOperation/delete-failed-001');
        $model->setStatus(BulkOperationStatus::Failed);
        $service->saveBulkOperation($model, false);

        $result = $service->deleteBulkOperationById($model->id);

        self::assertTrue($result);
        self::assertNull($service->getBulkOperationByShopifyId('gid://shopify/BulkOperation/delete-failed-001'));
    }

    // -------------------------------------------------------------------------
    // handleBulkOperationFinished
    // -------------------------------------------------------------------------

    public function testHandleBulkOperationFinishedIgnoresPayloadWithoutAdminGraphqlApiId(): void
    {
        // Should not throw
        Plugin::getInstance()->getBulkOperations()->handleBulkOperationFinished([]);
        Plugin::getInstance()->getBulkOperations()->handleBulkOperationFinished(['status' => 'completed']);

        $this->assertTrue(true);
    }

    public function testHandleBulkOperationFinishedIgnoresUnknownShopifyId(): void
    {
        Plugin::getInstance()->set('api', $this->makeEmpty(Api::class));

        Plugin::getInstance()->getBulkOperations()->handleBulkOperationFinished([
            'admin_graphql_api_id' => 'gid://shopify/BulkOperation/does-not-exist',
        ]);

        $this->assertTrue(true);
    }

    public function testHandleBulkOperationFinishedMarksCanceledAsCompleted(): void
    {
        $gid = 'gid://shopify/BulkOperation/cancel-test-001';
        $service = Plugin::getInstance()->getBulkOperations();
        $model = $this->_makeCreatedOp($gid);
        $service->saveBulkOperation($model, false);

        Plugin::getInstance()->set('api', $this->makeEmpty(Api::class, [
            'query' => fn() => ['status' => 'CANCELED', 'errorCode' => null, 'url' => null, 'objectCount' => 0],
        ]));

        $service->handleBulkOperationFinished(['admin_graphql_api_id' => $gid]);

        $reloaded = $service->getBulkOperationByShopifyId($gid);
        self::assertNotNull($reloaded);
        self::assertEquals(BulkOperationStatus::Completed, $reloaded->getStatus());
        self::assertEquals('CANCELED', $reloaded->shopifyStatus);
    }

    public function testHandleBulkOperationFinishedStoresUrlAndObjectCountWhenCompleted(): void
    {
        $gid = 'gid://shopify/BulkOperation/complete-test-001';
        $dataUrl = 'https://storage.example.com/results.jsonl';
        $service = Plugin::getInstance()->getBulkOperations();

        // A Processing op already in flight prevents queueNextBulkOperation from
        // pushing a queue job (which would try to download from the fake URL).
        $blocker = $this->_makeQueuedOp('gid://shopify/BulkOperation/blocker-001');
        $blocker->setStatus(BulkOperationStatus::Processing);
        $service->saveBulkOperation($blocker, false);

        $model = $this->_makeCreatedOp($gid);
        $service->saveBulkOperation($model, false);

        Plugin::getInstance()->set('api', $this->makeEmpty(Api::class, [
            'query' => fn() => ['status' => 'COMPLETED', 'url' => $dataUrl, 'objectCount' => 42, 'errorCode' => null],
        ]));

        $service->handleBulkOperationFinished(['admin_graphql_api_id' => $gid]);

        $reloaded = $service->getBulkOperationByShopifyId($gid);
        self::assertNotNull($reloaded);
        self::assertEquals('COMPLETED', $reloaded->shopifyStatus);
        self::assertEquals($dataUrl, $reloaded->url);
        self::assertEquals(42, $reloaded->objectCount);
    }

    // -------------------------------------------------------------------------
    // queueNextBulkOperation
    // -------------------------------------------------------------------------

    public function testQueueNextBulkOperationReturnsTrueWhenNothingQueued(): void
    {
        // Fixture data is all 'completed' - no created/queued ops
        $result = Plugin::getInstance()->getBulkOperations()->queueNextBulkOperation();
        self::assertTrue($result);
    }

    public function testQueueNextBulkOperationReturnsFalseWhenAlreadyProcessing(): void
    {
        $service = Plugin::getInstance()->getBulkOperations();
        $model = $this->_makeQueuedOp('gid://shopify/BulkOperation/processing-check-001');
        $model->setStatus(BulkOperationStatus::Processing);
        $service->saveBulkOperation($model, false);

        $result = $service->queueNextBulkOperation();

        self::assertFalse($result);
    }

    public function testQueueNextBulkOperationBailsWhenMutexCannotBeAcquired(): void
    {
        Craft::$app->set('mutex', $this->makeEmpty(Mutex::class, [
            'acquire' => fn() => false,
        ]));

        $service = Plugin::getInstance()->getBulkOperations();
        $gid = 'gid://shopify/BulkOperation/mutex-block-queue-001';
        $model = $this->_makeCreatedOp($gid);
        $model->url = 'https://storage.example.com/blocked.jsonl';
        $service->saveBulkOperation($model, false);

        $result = $service->queueNextBulkOperation();

        self::assertFalse($result);

        // Nothing should have been claimed while the lock was unavailable
        $reloaded = $service->getBulkOperationByShopifyId($gid);
        self::assertEquals(BulkOperationStatus::Created, $reloaded->getStatus());
    }

    // -------------------------------------------------------------------------
    // nextBulkOperation
    // -------------------------------------------------------------------------

    public function testNextBulkOperationBailsWhenMutexCannotBeAcquired(): void
    {
        Craft::$app->set('mutex', $this->makeEmpty(Mutex::class, [
            'acquire' => fn() => false,
        ]));

        $service = Plugin::getInstance()->getBulkOperations();
        $gid = 'gid://shopify/BulkOperation/mutex-block-next-001';
        $model = $this->_makeQueuedOp($gid);
        $service->saveBulkOperation($model, false);

        // The Shopify API should never be touched if the lock can't be acquired
        Plugin::getInstance()->set('api', $this->makeEmpty(Api::class, [
            'query' => function() {
                self::fail('The Shopify API should not be called when the mutex cannot be acquired.');
            },
        ]));

        $result = $service->nextBulkOperation();

        self::assertFalse($result);

        $reloaded = $service->getBulkOperationByShopifyId($gid);
        self::assertEquals(BulkOperationStatus::Queued, $reloaded->getStatus());
    }

    /**
     * Regression test for craftcms/shopify#224: under a backlog, the newest queued operation
     * used to win (`id DESC` + `.one()`), which could starve older ones indefinitely.
     */
    public function testNextBulkOperationProcessesOldestQueuedOperationFirst(): void
    {
        $service = Plugin::getInstance()->getBulkOperations();

        $older = $this->_makeQueuedOp('gid://shopify/BulkOperation/fifo-older-001');
        $older->query = 'query { older }';
        $service->saveBulkOperation($older, false);

        $newer = $this->_makeQueuedOp('gid://shopify/BulkOperation/fifo-newer-001');
        $newer->query = 'query { newer }';
        $service->saveBulkOperation($newer, false);

        self::assertLessThan($newer->id, $older->id, 'Test setup assumption: the older op must have the lower id.');

        $sentQueries = [];
        Plugin::getInstance()->set('api', $this->makeEmpty(Api::class, [
            'query' => function($query, $variables = null) use (&$sentQueries) {
                if (is_array($variables) && array_key_exists('query', $variables)) {
                    // The bulkOperationRunQuery mutation
                    $sentQueries[] = $variables['query'];
                    return [
                        'bulkOperation' => ['id' => 'gid://shopify/BulkOperation/fifo-started-001', 'status' => 'CREATED', 'type' => 'QUERY'],
                        'userErrors' => [],
                    ];
                }
                // The "anything already running on Shopify?" guard query
                return ['edges' => []];
            },
        ]));

        $result = $service->nextBulkOperation();

        self::assertTrue($result);
        self::assertCount(1, $sentQueries);
        self::assertEquals('query { older }', $sentQueries[0]);
    }

    /**
     * Regression test for the dead API concurrency guard: `Api::query()` unwraps the response
     * down to `['edges' => [...]]`, so the guard must read `edges[0].node.status`, not a
     * nonexistent top-level `status` key.
     */
    public function testNextBulkOperationBailsWhenShopifyReportsAnOperationAlreadyRunning(): void
    {
        $service = Plugin::getInstance()->getBulkOperations();
        $queued = $this->_makeQueuedOp('gid://shopify/BulkOperation/guard-test-001');
        $service->saveBulkOperation($queued, false);

        $mutationCalled = false;
        Plugin::getInstance()->set('api', $this->makeEmpty(Api::class, [
            'query' => function($query, $variables = null) use (&$mutationCalled) {
                if (is_array($variables) && array_key_exists('query', $variables)) {
                    $mutationCalled = true;
                    return ['bulkOperation' => ['id' => 'x', 'status' => 'CREATED', 'type' => 'QUERY'], 'userErrors' => []];
                }
                return [
                    'edges' => [
                        ['node' => ['id' => 'gid://shopify/BulkOperation/already-running', 'status' => 'RUNNING', 'type' => 'QUERY']],
                    ],
                ];
            },
        ]));

        $result = $service->nextBulkOperation();

        self::assertFalse($result);
        self::assertFalse($mutationCalled, 'The mutation should not fire when Shopify reports an operation already running.');
    }

    // -------------------------------------------------------------------------
    // purgeBulkOperations
    // -------------------------------------------------------------------------

    public function testPurgeBulkOperationsMarksStaleCreatedAndProcessingRowsAsFailed(): void
    {
        $service = Plugin::getInstance()->getBulkOperations();

        // Stuck for 25 hours — past the 24-hour timeout, should be reaped
        $stale = $this->_makeCreatedOp('gid://shopify/BulkOperation/purge-stale-001');
        $service->saveBulkOperation($stale, false);
        $this->_backdateBulkOperation($stale->id, '-25 hours');

        // Only 1 hour old — still well within a realistic sync duration, should be left alone
        $recent = $this->_makeQueuedOp('gid://shopify/BulkOperation/purge-recent-001');
        $recent->setStatus(BulkOperationStatus::Processing);
        $service->saveBulkOperation($recent, false);
        $this->_backdateBulkOperation($recent->id, '-1 hour');

        $service->purgeBulkOperations();

        $reloadedStale = $service->getBulkOperationByShopifyId('gid://shopify/BulkOperation/purge-stale-001');
        self::assertNotNull($reloadedStale);
        self::assertEquals(BulkOperationStatus::Failed, $reloadedStale->getStatus());

        $reloadedRecent = $service->getBulkOperationByShopifyId('gid://shopify/BulkOperation/purge-recent-001');
        self::assertNotNull($reloadedRecent);
        self::assertEquals(BulkOperationStatus::Processing, $reloadedRecent->getStatus());
    }

    public function testPurgeBulkOperationsDeletesOldCompletedAndFailedRowsButKeepsRecentOnes(): void
    {
        $service = Plugin::getInstance()->getBulkOperations();

        $oldCompleted = $this->_makeQueuedOp('gid://shopify/BulkOperation/purge-old-completed-001');
        $oldCompleted->setStatus(BulkOperationStatus::Completed);
        $service->saveBulkOperation($oldCompleted, false);
        $this->_backdateBulkOperation($oldCompleted->id, '-8 days');

        $oldFailed = $this->_makeQueuedOp('gid://shopify/BulkOperation/purge-old-failed-001');
        $oldFailed->setStatus(BulkOperationStatus::Failed);
        $service->saveBulkOperation($oldFailed, false);
        $this->_backdateBulkOperation($oldFailed->id, '-8 days');

        $recentCompleted = $this->_makeQueuedOp('gid://shopify/BulkOperation/purge-recent-completed-001');
        $recentCompleted->setStatus(BulkOperationStatus::Completed);
        $service->saveBulkOperation($recentCompleted, false);

        $service->purgeBulkOperations();

        self::assertNull($service->getBulkOperationByShopifyId('gid://shopify/BulkOperation/purge-old-completed-001'));
        self::assertNull($service->getBulkOperationByShopifyId('gid://shopify/BulkOperation/purge-old-failed-001'));
        self::assertNotNull($service->getBulkOperationByShopifyId('gid://shopify/BulkOperation/purge-recent-completed-001'));
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function _makeQueuedOp(string $shopifyId): BulkOperation
    {
        $model = new BulkOperation();
        $model->shopifyId = $shopifyId;
        $model->query = 'query { products { edges { node { id } } } }';
        $model->clearData = 'none';
        $model->setStatus(BulkOperationStatus::Queued);
        return $model;
    }

    private function _makeCreatedOp(string $shopifyId): BulkOperation
    {
        $model = $this->_makeQueuedOp($shopifyId);
        $model->setStatus(BulkOperationStatus::Created);
        return $model;
    }

    /**
     * Builds a queued op the way `createBulkOperation()` actually does: no `id`, no `shopifyId`
     * (that's only assigned once Shopify accepts it). Deliberately doesn't use `_makeQueuedOp()`,
     * which always assigns a `shopifyId` up front.
     */
    private function _makeFreshQueuedOp(string $query): BulkOperation
    {
        $model = new BulkOperation();
        $model->query = $query;
        $model->clearData = 'none';
        $model->setStatus(BulkOperationStatus::Queued);
        return $model;
    }

    /**
     * `saveBulkOperation()` always stamps `dateUpdated` to "now" unless the caller explicitly
     * changes it, so purge-timeout tests write directly to the DB to simulate an old row.
     */
    private function _backdateBulkOperation(int $id, string $relativeTime): void
    {
        \Yii::$app->db->createCommand()->update(
            Table::BULK_OPERATIONS,
            ['dateUpdated' => date('Y-m-d H:i:s', strtotime($relativeTime))],
            ['id' => $id],
        )->execute();
    }
}
