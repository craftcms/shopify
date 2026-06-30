<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\shopify\tests\unit\jobs;

use Codeception\Test\Unit;
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

        $record = ShopifyData::findOne(['shopifyGid' => $variantGid, 'parentId' => $productGid]);
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

        $records = ShopifyData::find()->where(['shopifyGid' => $variantGid, 'parentId' => $productGid])->all();
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
            'deleteShopifyDataByShopifyGid' => fn() => null,
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
        $productRow = ShopifyData::find()->where(['shopifyGid' => $productGid, 'type' => 'Product'])->one();
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
            'shopifyGid' => 'gid://shopify/Product/before-clear-test-001',
            'type' => 'Product',
            'data' => json_encode(['id' => 'gid://shopify/Product/before-clear-test-001']),
            'parentId' => null,
            'uid' => 'before-clear-uid-001',
            'dateCreated' => date('Y-m-d H:i:s'),
            'dateUpdated' => date('Y-m-d H:i:s'),
        ])->execute();

        $service = Plugin::getInstance()->getBulkOperations();
        $model = new BulkOperation();
        $model->shopifyGid = self::BULK_OP_GID;
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
            'shopifyGid' => 'gid://shopify/Product/before-none-test-001',
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
        $model->shopifyGid = self::BULK_OP_GID;
        $model->query = 'query {}';
        $model->clearData = 'none';
        $model->setStatus(BulkOperationStatus::Created);
        $service->saveBulkOperation($model, false);

        $job = $this->_makeJob(clearData: 'none');
        $job->callBefore();

        self::assertEquals($countBefore, ShopifyData::find()->count());
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function _makeJob(string $clearData = 'none'): TestableProcessBulkOperationData
    {
        return new TestableProcessBulkOperationData([
            'bulkOperationShopifyGid' => self::BULK_OP_GID,
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
        $bulkOperation = Plugin::getInstance()->getBulkOperations()->getBulkOperationByShopifyGid($this->bulkOperationShopifyGid);

        if (!$bulkOperation) {
            return;
        }

        $bulkOperation->setStatus(BulkOperationStatus::Processing);
        Plugin::getInstance()->getBulkOperations()->saveBulkOperation($bulkOperation, false);

        if ($this->clearData === \craft\shopify\records\BulkOperation::CLEAR_DATA_ALL) {
            ShopifyData::deleteAll();
        } elseif ($this->clearData !== \craft\shopify\records\BulkOperation::CLEAR_DATA_NONE) {
            Plugin::getInstance()->getProducts()->deleteShopifyDataByShopifyGid($this->clearData);
        }
    }
}
