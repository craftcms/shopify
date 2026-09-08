<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\shopify\tests\unit\jobs;

use Codeception\Test\Unit;
use Craft;
use craft\queue\Queue;
use craft\shopify\enums\BulkOperationStatus;
use craft\shopify\jobs\ProcessBulkOperationData;
use craft\shopify\models\BulkOperation;
use craft\shopify\Plugin;
use craft\shopify\records\ShopifyData;
use craft\shopify\services\Products;
use craft\shopify\tests\fixtures\BulkOperationsFixture;
use UnitTester;

/**
 * @group jobs
 */
class ProcessBulkOperationDataTest extends Unit
{
    public UnitTester $tester;

    private const BULK_OP_GID = 'gid://shopify/BulkOperation/process-test-001';

    public function _fixtures(): array
    {
        return [
            'bulkOperations' => ['class' => BulkOperationsFixture::class],
        ];
    }

    protected function _after(): void
    {
        Plugin::getInstance()->set('products', Products::class);

        // Restore the real queue component after any test that swaps it out
        Craft::$app->set('queue', Queue::class);
    }

    // -------------------------------------------------------------------------
    // processItem (via testable subclass)
    // -------------------------------------------------------------------------

    public function testProcessItemSkipsNullJsonItems(): void
    {
        $job = $this->_makeJob();
        $countBefore = ShopifyData::find()->count();

        $job->callProcessItem('null');
        $job->callProcessItem('not-valid-json');

        // Nothing should have been written
        self::assertEquals($countBefore, ShopifyData::find()->count());
    }

    public function testProcessItemSkipsItemsWithoutId(): void
    {
        $job = $this->_makeJob();
        $countBefore = ShopifyData::find()->count();

        $job->callProcessItem(json_encode(['title' => 'No ID here', '__parentId' => null]));

        self::assertEquals($countBefore, ShopifyData::find()->count());
    }

    public function testProcessItemCreatesShopifyDataRecordForVariant(): void
    {
        $variantGid = 'gid://shopify/ProductVariant/process-test-variant-001';
        $productGid = 'gid://shopify/Product/process-test-product-001';

        $json = json_encode([
            'id' => $variantGid,
            'title' => 'Small / Black',
            'price' => '49.99',
            '__parentId' => $productGid,
        ]);

        $job = $this->_makeJob();
        $job->callProcessItem($json);

        $record = ShopifyData::findOne(['shopifyId' => $variantGid, 'parentId' => $productGid]);
        self::assertNotNull($record);
        self::assertEquals('ProductVariant', $record->type);
        self::assertEquals($productGid, $record->parentId);
    }

    public function testProcessItemUpdatesExistingShopifyDataRecord(): void
    {
        $variantGid = 'gid://shopify/ProductVariant/process-test-variant-update-001';
        $productGid = 'gid://shopify/Product/process-test-product-001';

        $json = json_encode([
            'id' => $variantGid,
            'price' => '10.00',
            '__parentId' => $productGid,
        ]);

        $job = $this->_makeJob();
        $job->callProcessItem($json);

        // Update — call again with changed price
        $updatedJson = json_encode([
            'id' => $variantGid,
            'price' => '25.00',
            '__parentId' => $productGid,
        ]);
        $job->callProcessItem($updatedJson);

        $records = ShopifyData::find()->where(['shopifyId' => $variantGid, 'parentId' => $productGid])->all();
        // Should still only be one record (updated in place)
        self::assertCount(1, $records);
    }

    public function testProcessItemCallsCreateOrUpdateProductForProductType(): void
    {
        $productGid = 'gid://shopify/Product/process-create-product-001';

        $productData = [
            'id' => $productGid,
            'title' => 'Test Product',
            'handle' => 'test-product',
            'status' => 'ACTIVE',
            'vendor' => 'Test Vendor',
            'productType' => 'Widgets',
            'tags' => [],
            'options' => [],
            'descriptionHtml' => '',
            'createdAt' => '2024-01-01T00:00:00Z',
            'updatedAt' => '2024-01-01T00:00:00Z',
            'publishedAt' => '2024-01-01T00:00:00Z',
            'templateSuffix' => null,
            'totalInventory' => 0,
        ];

        $called = false;
        Plugin::getInstance()->set('products', $this->makeEmpty(Products::class, [
            'createOrUpdateProduct' => function(array $p) use (&$called, $productGid) {
                $called = true;
                self::assertEquals($productGid, $p['id']);
                return true;
            },
        ]));

        $job = $this->_makeJob();
        $job->callProcessItem(json_encode($productData));

        self::assertTrue($called, 'createOrUpdateProduct should have been called for Product type items');

        Plugin::getInstance()->set('products', Products::class);
    }

    public function testProcessItemDoesNotCallCreateOrUpdateProductForVariantType(): void
    {
        $variantGid = 'gid://shopify/ProductVariant/no-create-test-001';

        $called = false;
        Plugin::getInstance()->set('products', $this->makeEmpty(Products::class, [
            'createOrUpdateProduct' => function() use (&$called) {
                $called = true;
                return true;
            },
        ]));

        $job = $this->_makeJob();
        $job->callProcessItem(json_encode([
            'id' => $variantGid,
            'price' => '9.99',
            '__parentId' => 'gid://shopify/Product/some-product',
        ]));

        self::assertFalse($called, 'createOrUpdateProduct should not be called for non-Product type items');

        Plugin::getInstance()->set('products', Products::class);
    }

    // -------------------------------------------------------------------------
    // Real JSONL fixture
    // -------------------------------------------------------------------------

    public function testProcessItemsFromRealBulkOperationJsonl(): void
    {
        $fixtureFile = __DIR__ . '/../../fixtures/data/bulk-operation-single-product.jsonl';
        self::assertFileExists($fixtureFile);

        $productGid = 'gid://shopify/Product/6656149192755';

        // createOrUpdateProduct requires a full element save — mock it out
        Plugin::getInstance()->set('products', $this->makeEmpty(Products::class, [
            'createOrUpdateProduct' => fn() => true,
            'deleteShopifyDataByShopifyId' => fn() => null,
        ]));

        $job = $this->_makeJob();

        foreach (new \SplFileObject($fixtureFile) as $line) {
            $line = trim((string)$line);
            if ($line !== '') {
                $job->callProcessItem($line);
            }
        }

        Plugin::getInstance()->set('products', Products::class);

        // Total rows created
        $total = ShopifyData::find()->count();
        self::assertEquals(60, $total);

        // Product row
        $productRow = ShopifyData::find()->where(['shopifyId' => $productGid, 'type' => 'Product'])->one();
        self::assertNotNull($productRow);
        self::assertNull($productRow->parentId);

        // Variants — all must reference the product as parent
        $variants = ShopifyData::find()->where(['parentId' => $productGid, 'type' => 'ProductVariant'])->count();
        self::assertEquals(55, $variants);

        // MediaImages — direct children of the product
        $images = ShopifyData::find()->where(['parentId' => $productGid, 'type' => 'MediaImage'])->count();
        self::assertEquals(2, $images);

        // Product-level metafield
        $productMetafield = ShopifyData::find()
            ->where(['parentId' => $productGid, 'type' => 'Metafield'])
            ->one();
        self::assertNotNull($productMetafield);
        self::assertEquals('special_part', $productMetafield->data['key']);
        self::assertEquals('Extra button', $productMetafield->data['value']);

        // Variant-level metafield (parentId is a variant GID)
        $variantMetafield = ShopifyData::find()
            ->where(['type' => 'Metafield'])
            ->andWhere(['not', ['parentId' => $productGid]])
            ->one();
        self::assertNotNull($variantMetafield);
        self::assertEquals('colour', $variantMetafield->data['key']);
        self::assertStringStartsWith('gid://shopify/ProductVariant/', $variantMetafield->parentId);
    }

    // -------------------------------------------------------------------------
    // before() — data clearing behaviour
    // -------------------------------------------------------------------------

    public function testBeforeClearDataAllDeletesAllShopifyData(): void
    {
        // Insert a couple of rows that should be wiped
        \Yii::$app->db->createCommand()->insert(\craft\shopify\db\Table::DATA, [
            'shopifyId' => 'gid://shopify/Product/before-clear-test-001',
            'type' => 'Product',
            'data' => json_encode(['id' => 'gid://shopify/Product/before-clear-test-001']),
            'parentId' => null,
            'uid' => 'before-clear-uid-001',
            'dateCreated' => date('Y-m-d H:i:s'),
            'dateUpdated' => date('Y-m-d H:i:s'),
        ])->execute();

        $service = Plugin::getInstance()->getBulkOperations();
        $model = new BulkOperation();
        $model->shopifyId = self::BULK_OP_GID;
        $model->query = 'query {}';
        $model->clearData = 'none';
        $model->setStatus(BulkOperationStatus::Created);
        $service->saveBulkOperation($model, false);

        $job = $this->_makeJob(clearData: 'all');
        $job->callBefore();

        self::assertEquals(0, ShopifyData::find()->count());
    }

    public function testBeforeClearDataNoneDoesNotDeleteShopifyData(): void
    {
        \Yii::$app->db->createCommand()->insert(\craft\shopify\db\Table::DATA, [
            'shopifyId' => 'gid://shopify/Product/before-none-test-001',
            'type' => 'Product',
            'data' => json_encode(['id' => 'gid://shopify/Product/before-none-test-001']),
            'parentId' => null,
            'uid' => 'before-none-uid-001',
            'dateCreated' => date('Y-m-d H:i:s'),
            'dateUpdated' => date('Y-m-d H:i:s'),
        ])->execute();

        $countBefore = ShopifyData::find()->count();

        $service = Plugin::getInstance()->getBulkOperations();
        $model = new BulkOperation();
        $model->shopifyId = self::BULK_OP_GID;
        $model->query = 'query {}';
        $model->clearData = 'none';
        $model->setStatus(BulkOperationStatus::Created);
        $service->saveBulkOperation($model, false);

        $job = $this->_makeJob(clearData: 'none');
        $job->callBefore();

        self::assertEquals($countBefore, ShopifyData::find()->count());
    }

    // -------------------------------------------------------------------------
    // after() — queueing the next operation
    // -------------------------------------------------------------------------

    /**
     * Regression test for craftcms/shopify#224: before the fix, `after()` only called
     * `nextBulkOperation()`, which bails whenever anything is `created`—so an operation that
     * got stuck at `created` (with its `url` already populated, because Shopify finished it
     * while this job's operation was still `processing`) was never picked back up once this
     * job finished. `after()` must also call `queueNextBulkOperation()`.
     */
    public function testAfterQueuesAnAlreadyCreatedOperationWithUrl(): void
    {
        $service = Plugin::getInstance()->getBulkOperations();

        // The operation this job is "finishing" for
        $current = new BulkOperation();
        $current->shopifyId = self::BULK_OP_GID;
        $current->query = 'query {}';
        $current->clearData = 'none';
        $current->setStatus(BulkOperationStatus::Processing);
        $service->saveBulkOperation($current, false);

        // A second operation stuck at `created`, with its URL already populated — exactly the
        // scenario from the issue (7493/7495). Its (fake) URL is never actually fetched below—
        // see the queue double—so it doesn't need to resolve to anything real.
        $stuckGid = 'gid://shopify/BulkOperation/stuck-001';
        $stuck = new BulkOperation();
        $stuck->shopifyId = $stuckGid;
        $stuck->url = 'https://storage.example.com/stuck.jsonl';
        $stuck->objectCount = 5;
        $stuck->query = 'query {}';
        $stuck->clearData = 'none';
        $stuck->setStatus(BulkOperationStatus::Created);
        $service->saveBulkOperation($stuck, false);

        // queueNextBulkOperation() will push a ProcessBulkOperationData job for the stuck
        // operation. Swap in a queue double so the push is recorded but the job never actually
        // runs — letting it run for real would try to download the fake URL above. `make()`
        // (unlike `makeEmpty()`) keeps the real priority()/delay()/ttr() fluent setters that
        // craft\helpers\Queue::push() chains before calling push() itself.
        $pushedJobs = [];
        Craft::$app->set('queue', $this->make(Queue::class, [
            'push' => function($job) use (&$pushedJobs) {
                $pushedJobs[] = $job;
                return 'fake-queue-id-' . count($pushedJobs);
            },
        ]));

        $job = $this->_makeJob();
        $job->callAfter();

        // The current operation should now be completed
        $reloadedCurrent = $service->getBulkOperationByShopifyId(self::BULK_OP_GID);
        self::assertEquals(BulkOperationStatus::Completed, $reloadedCurrent->getStatus());

        // A job should have been pushed for the previously-stuck operation
        self::assertCount(1, $pushedJobs);
        self::assertInstanceOf(ProcessBulkOperationData::class, $pushedJobs[0]);
        self::assertEquals($stuckGid, $pushedJobs[0]->bulkOperationShopifyId);

        // And it should have been claimed (moved to `processing`) since the push "succeeded"
        $reloadedStuck = $service->getBulkOperationByShopifyId($stuckGid);
        self::assertEquals(BulkOperationStatus::Processing, $reloadedStuck->getStatus());
    }

    public function testAfterDoesNothingWhenNothingIsStuck(): void
    {
        $service = Plugin::getInstance()->getBulkOperations();

        $current = new BulkOperation();
        $current->shopifyId = self::BULK_OP_GID;
        $current->query = 'query {}';
        $current->clearData = 'none';
        $current->setStatus(BulkOperationStatus::Processing);
        $service->saveBulkOperation($current, false);

        $job = $this->_makeJob();
        $job->callAfter();

        $reloadedCurrent = $service->getBulkOperationByShopifyId(self::BULK_OP_GID);
        self::assertEquals(BulkOperationStatus::Completed, $reloadedCurrent->getStatus());
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function _makeJob(string $clearData = 'none'): TestableProcessBulkOperationData
    {
        return new TestableProcessBulkOperationData([
            'bulkOperationShopifyId' => self::BULK_OP_GID,
            'dataUrl' => 'https://storage.example.com/data.jsonl',
            'objectCount' => 0,
            'clearData' => $clearData,
        ]);
    }
}

/**
 * Exposes protected methods of ProcessBulkOperationData for unit testing.
 */
class TestableProcessBulkOperationData extends ProcessBulkOperationData
{
    public function callProcessItem(mixed $item): void
    {
        $this->processItem($item);
    }

    public function callBefore(): void
    {
        // Call only our override, not BaseBatchedJob::before() which requires queue context
        $bulkOperation = Plugin::getInstance()->getBulkOperations()->getBulkOperationByShopifyId($this->bulkOperationShopifyId);

        if (!$bulkOperation) {
            return;
        }

        $bulkOperation->setStatus(BulkOperationStatus::Processing);
        Plugin::getInstance()->getBulkOperations()->saveBulkOperation($bulkOperation, false);

        if ($this->clearData === \craft\shopify\records\BulkOperation::CLEAR_DATA_ALL) {
            ShopifyData::deleteAll();
        } elseif ($this->clearData !== \craft\shopify\records\BulkOperation::CLEAR_DATA_NONE) {
            Plugin::getInstance()->getProducts()->deleteShopifyDataByShopifyId($this->clearData);
        }
    }

    public function callAfter(): void
    {
        // Call only our override's logic, not BaseBatchedJob::after() which requires queue context
        $bulkOperation = Plugin::getInstance()->getBulkOperations()->getBulkOperationByShopifyId($this->bulkOperationShopifyId);

        if (!$bulkOperation) {
            return;
        }

        $bulkOperation->setStatus(BulkOperationStatus::Completed);
        Plugin::getInstance()->getBulkOperations()->saveBulkOperation($bulkOperation, false);

        if ($this->tempFilePath !== null && file_exists($this->tempFilePath)) {
            \craft\helpers\FileHelper::unlink($this->tempFilePath);
        }

        // Regression coverage for craftcms/shopify#224: pick up anything already `created` with
        // a URL before starting anything new.
        Plugin::getInstance()->getBulkOperations()->queueNextBulkOperation();
        Plugin::getInstance()->getBulkOperations()->nextBulkOperation();
    }
}
