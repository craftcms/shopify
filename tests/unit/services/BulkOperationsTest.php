<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\shopify\tests\unit\services;

use Codeception\Test\Unit;
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
        $shopifyIds = $ops->pluck('shopifyGid')->all();

        self::assertContains(self::FIXTURE_GID, $shopifyIds);
    }

    // -------------------------------------------------------------------------
    // getBulkOperationByShopifyGid
    // -------------------------------------------------------------------------

    public function testGetBulkOperationByShopifyGidFindsKnownRecord(): void
    {
        $op = Plugin::getInstance()->getBulkOperations()->getBulkOperationByShopifyGid(self::FIXTURE_GID);

        self::assertNotNull($op);
        self::assertInstanceOf(BulkOperation::class, $op);
        self::assertEquals(self::FIXTURE_GID, $op->shopifyGid);
        self::assertEquals(BulkOperationStatus::Completed, $op->getStatus());
    }

    public function testGetBulkOperationByShopifyGidReturnsNullForUnknownId(): void
    {
        $op = Plugin::getInstance()->getBulkOperations()->getBulkOperationByShopifyGid('gid://shopify/BulkOperation/does-not-exist');

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
        $op = $service->getBulkOperationByShopifyGid(self::FIXTURE_GID);

        $op->objectCount = 9999;
        $service->saveBulkOperation($op, false);

        $reloaded = $service->getBulkOperationByShopifyGid(self::FIXTURE_GID);
        self::assertEquals(9999, $reloaded->objectCount);
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
        self::assertNull($service->getBulkOperationByShopifyGid('gid://shopify/BulkOperation/delete-test-001'));
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
        self::assertNotNull($service->getBulkOperationByShopifyGid('gid://shopify/BulkOperation/processing-test-001'));
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

        $reloaded = $service->getBulkOperationByShopifyGid($gid);
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

        $reloaded = $service->getBulkOperationByShopifyGid($gid);
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

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function _makeQueuedOp(string $gid): BulkOperation
    {
        $model = new BulkOperation();
        $model->shopifyGid = $gid;
        $model->query = 'query { products { edges { node { id } } } }';
        $model->clearData = 'none';
        $model->setStatus(BulkOperationStatus::Queued);
        return $model;
    }

    private function _makeCreatedOp(string $gid): BulkOperation
    {
        $model = $this->_makeQueuedOp($gid);
        $model->setStatus(BulkOperationStatus::Created);
        return $model;
    }
}
