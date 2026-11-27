<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\shopify\tests\unit\jobs;

use Codeception\Test\Unit;
use Composer\InstalledVersions;
use Craft;
use craft\base\Batchable;
use craft\db\Query;
use craft\db\Table as CraftTable;
use craft\helpers\Assets;
use craft\helpers\Db;
use craft\helpers\Queue as QueueHelper;
use craft\queue\BaseBatchedJob;
use craft\queue\Queue;
use craft\shopify\api\BulkDataBatcher;
use craft\shopify\elements\Product;
use craft\shopify\enums\BulkOperationStatus;
use craft\shopify\jobs\ProcessBulkOperationData;
use craft\shopify\models\BulkOperation;
use craft\shopify\Plugin;
use craft\shopify\records\ShopifyData;
use craft\shopify\services\Api;
use craft\shopify\services\Products;
use craft\shopify\tests\fixtures\BulkOperationsFixture;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use UnitTester;

/**
 * @group jobs
 */
class ProcessBulkOperationDataTest extends Unit
{
    public UnitTester $tester;

    private const BULK_OP_GID = 'gid://shopify/BulkOperation/process-test-001';

    private ?string $_originalUriFormat = null;

    public function _fixtures(): array
    {
        return [
            'bulkOperations' => ['class' => BulkOperationsFixture::class],
        ];
    }

    protected function _after(): void
    {
        Plugin::getInstance()->set('products', Products::class);
        Plugin::getInstance()->set('api', Api::class);

        if ($this->_originalUriFormat !== null) {
            Plugin::getInstance()->getSettings()->uriFormat = $this->_originalUriFormat;
        }

        // Restore the real queue component after any test that swaps it out
        Craft::$app->set('queue', Queue::class);

        Craft::$container->clear(Client::class);
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
    // execute() — batched processing of the data file
    // -------------------------------------------------------------------------

    public function testLoadDataReadsTheFileInReverse(): void
    {
        $job = $this->_makeJob();
        $job->objectCount = 60;
        $job->tempFilePath = $this->_copyFixtureToTempFile();

        $batcher = $job->callLoadData();

        self::assertInstanceOf(BulkDataBatcher::class, $batcher);
        self::assertTrue($batcher->reverse);
        self::assertSame(60, $batcher->count());
    }

    /**
     * The fixture has 60 lines, so a batch size of 10 also covers the last line being skipped
     * when the line count was a multiple of the batch size.
     */
    public function testExecuteProcessesEveryLineOnceAcrossBatches(): void
    {
        $productGid = 'gid://shopify/Product/6656149192755';
        $savedProducts = [];
        Plugin::getInstance()->set('products', $this->makeEmpty(Products::class, [
            'createOrUpdateProduct' => function(array $product) use (&$savedProducts) {
                $savedProducts[] = $product['id'];
                return true;
            },
        ]));

        $this->_runJob($this->_makeExecutableJob($this->_copyFixtureToTempFile(), 60, 'all', batchSize: 10));

        self::assertSame([$productGid], $savedProducts);
        self::assertEquals(60, ShopifyData::find()->count());
        self::assertEquals(58, ShopifyData::find()->where(['parentId' => $productGid])->count());
    }

    public function testExecuteStoresChildrenInTheOrderTheyAppearInTheFile(): void
    {
        $productGid = 'gid://shopify/Product/order-test-001';
        $imageGids = [];
        $lines = [json_encode(['id' => $productGid])];
        foreach (range(1, 5) as $i) {
            $imageGids[] = "gid://shopify/MediaImage/order-test-$i";
            $lines[] = json_encode(['id' => end($imageGids), '__parentId' => $productGid]);
        }

        $filePath = Assets::tempFilePath('jsonl');
        file_put_contents($filePath, implode("\n", $lines) . "\n");

        Plugin::getInstance()->set('products', $this->makeEmpty(Products::class, [
            'createOrUpdateProduct' => true,
        ]));

        $this->_runJob($this->_makeExecutableJob($filePath, count($lines), 'all', batchSize: 2));

        $images = Plugin::getInstance()->getApi()->getShopifyDataByType('MediaImage', $productGid);

        self::assertSame($imageGids, $images->pluck('id')->all());
    }

    /**
     * A TTR of zero makes every batch stop after its first item, leaving the rest of its slice unprocessed.
     */
    public function testExecuteResumesFromStoredPositionWhenBatchesStopEarly(): void
    {
        $productGid = 'gid://shopify/Product/6656149192755';
        $savedProducts = [];
        Plugin::getInstance()->set('products', $this->makeEmpty(Products::class, [
            'createOrUpdateProduct' => function(array $product) use (&$savedProducts) {
                $savedProducts[] = $product['id'];
                return true;
            },
        ]));

        $filePath = $this->_copyFixtureToTempFile();
        $job = $this->_makeExecutableJob($filePath, 60, 'all', batchSize: 10);
        $job->ttr = 0;

        $queue = new RecordingQueue();
        $job->execute($queue);
        $nextJob = $queue->popJob();

        self::assertSame(1, $nextJob->itemOffset);
        self::assertSame(strrpos(rtrim(file_get_contents($filePath)), "\n") + 1, $nextJob->dataFilePosition);

        while ($nextJob) {
            $nextJob->execute($queue);
            $nextJob = $queue->popJob();
        }

        self::assertSame([$productGid], $savedProducts);
        self::assertEquals(60, ShopifyData::find()->count());
    }

    public function testExecuteSavesProductsAfterAllOfTheirChildren(): void
    {
        $productGid = 'gid://shopify/Product/6656149192755';
        $childCounts = [];
        Plugin::getInstance()->set('products', $this->makeEmpty(Products::class, [
            'createOrUpdateProduct' => function(array $product) use (&$childCounts) {
                $childCounts[$product['id']] = (int)ShopifyData::find()->where(['parentId' => $product['id']])->count();
                return true;
            },
        ]));

        $this->_runJob($this->_makeExecutableJob($this->_copyFixtureToTempFile(), 60, 'all', batchSize: 7));

        self::assertSame([$productGid => 58], $childCounts);
    }

    /**
     * Regression test for craftcms/shopify#230: products were saved before their metafields were stored,
     * so a URI format referencing metafields rendered empty.
     */
    public function testFullSyncRendersUriFormatUsingMetafields(): void
    {
        $this->_setUriFormat('{metafields.special_part|kebab}');

        $this->_runJob($this->_makeExecutableJob($this->_copyFixtureToTempFile(), 60, 'all', batchSize: 25));

        self::assertSame('extra-button', $this->_findProduct('gid://shopify/Product/6656149192755')?->uri);
    }

    /**
     * Regression test for craftcms/shopify#230: a per-product sync deletes the product’s stored data before
     * processing, so existing products lost their URIs.
     */
    public function testProductSyncKeepsUriFormatUsingMetafields(): void
    {
        $productGid = 'gid://shopify/Product/6656149192755';
        $this->_setUriFormat('{metafields.special_part|kebab}');

        $this->_runJob($this->_makeExecutableJob($this->_copyFixtureToTempFile(), 60, 'all'));
        self::assertSame('extra-button', $this->_findProduct($productGid)?->uri);

        $this->_runJob($this->_makeExecutableJob($this->_copyFixtureToTempFile(), 60, $productGid, batchSize: 25));

        self::assertSame('extra-button', $this->_findProduct($productGid)?->uri);
    }

    /**
     * Shopify only guarantees that child objects come after their parent, not that they directly follow it.
     */
    public function testExecuteSavesProductsWhoseChildrenAreNotGrouped(): void
    {
        $this->_setUriFormat('{metafields.special_part|kebab}');

        $fixtureLines = file($this->_fixturePath(), FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $firstProduct = json_decode($fixtureLines[0], true);
        $secondProduct = array_merge($firstProduct, [
            'id' => 'gid://shopify/Product/230230230',
            'handle' => 'second-product',
            'title' => 'Second Product',
        ]);

        $lines = [
            $firstProduct,
            $secondProduct,
            ['id' => 'gid://shopify/Metafield/230230231', 'key' => 'special_part', 'value' => 'Second part', '__parentId' => $secondProduct['id']],
            ['id' => 'gid://shopify/Metafield/230230232', 'key' => 'special_part', 'value' => 'First part', '__parentId' => $firstProduct['id']],
        ];
        $filePath = Assets::tempFilePath('jsonl');
        file_put_contents($filePath, implode("\n", array_map(fn(array $line) => json_encode($line), $lines)) . "\n");

        $this->_runJob($this->_makeExecutableJob($filePath, count($lines), 'all', batchSize: 1));

        self::assertSame('first-part', $this->_findProduct($firstProduct['id'])?->uri);
        self::assertSame('second-part', $this->_findProduct($secondProduct['id'])?->uri);
    }

    public function testFailedDownloadLeavesNoFileBehind(): void
    {
        $job = $this->_makeJob();
        $job->dataUrl = 'http://127.0.0.1:1/data.jsonl';

        try {
            $job->execute(new RecordingQueue());
            self::fail('Expected the download to fail.');
        } catch (\GuzzleHttp\Exception\GuzzleException) {
        }

        self::assertNotNull($job->tempFilePath);
        self::assertFileDoesNotExist($job->tempFilePath);
        self::assertFileDoesNotExist($job->tempFilePath . '.download');
    }

    /**
     * Regression coverage for craftcms/shopify#243: Craft builds the description before a job is pushed and
     * before it executes, outside the job’s error handling.
     */
    public function testGetDescriptionDoesNotDownloadTheDataFile(): void
    {
        $job = $this->_makeJob();
        $job->dataUrl = 'http://127.0.0.1:1/data.jsonl';
        $job->objectCount = 250;
        $job->batchSize = 100;

        self::assertSame('Processing bulk operation data (batch 1 of 3)', $job->getDescription());
        self::assertNull($job->tempFilePath);
    }

    /**
     * Regression coverage for craftcms/shopify#243: a queue worker may not have the temp directory that the
     * job was pushed with.
     */
    public function testExecuteCreatesAMissingTempDirectory(): void
    {
        $this->_mockDataFileResponse(file_get_contents($this->_fixturePath()));
        $filePath = Craft::$app->getPath()->getTempPath() . DIRECTORY_SEPARATOR . uniqid('missing', true) . DIRECTORY_SEPARATOR . 'data.jsonl';

        $this->_runJob($this->_makeExecutableJob($filePath, 60, 'all'));

        self::assertSame(60, (int)ShopifyData::find()->count());
        self::assertFileDoesNotExist($filePath);
    }

    public function testExecuteDownloadsTheDataFileWhenItHasNoTempFile(): void
    {
        $this->_mockDataFileResponse(file_get_contents($this->_fixturePath()));
        $job = $this->_makeExecutableJob($this->_copyFixtureToTempFile(), 60, 'all', 25);
        $job->tempFilePath = null;

        $this->_runJob($job);

        self::assertSame(60, (int)ShopifyData::find()->count());
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
        $current->shopifyGid = self::BULK_OP_GID;
        $current->query = 'query {}';
        $current->clearData = 'none';
        $current->setStatus(BulkOperationStatus::Processing);
        $service->saveBulkOperation($current, false);

        // A second operation stuck at `created`, with its URL already populated — exactly the
        // scenario from the issue (7493/7495). Its (fake) URL is never actually fetched below—
        // see the queue double—so it doesn't need to resolve to anything real.
        $stuckGid = 'gid://shopify/BulkOperation/stuck-001';
        $stuck = new BulkOperation();
        $stuck->shopifyGid = $stuckGid;
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
        $reloadedCurrent = $service->getBulkOperationByShopifyGid(self::BULK_OP_GID);
        self::assertEquals(BulkOperationStatus::Completed, $reloadedCurrent->getStatus());

        // A job should have been pushed for the previously-stuck operation
        self::assertCount(1, $pushedJobs);
        self::assertInstanceOf(ProcessBulkOperationData::class, $pushedJobs[0]);
        self::assertEquals($stuckGid, $pushedJobs[0]->bulkOperationShopifyGid);

        // And it should have been claimed (moved to `processing`) since the push "succeeded"
        $reloadedStuck = $service->getBulkOperationByShopifyGid($stuckGid);
        self::assertEquals(BulkOperationStatus::Processing, $reloadedStuck->getStatus());
    }

    public function testAfterDoesNothingWhenNothingIsStuck(): void
    {
        $service = Plugin::getInstance()->getBulkOperations();

        $current = new BulkOperation();
        $current->shopifyGid = self::BULK_OP_GID;
        $current->query = 'query {}';
        $current->clearData = 'none';
        $current->setStatus(BulkOperationStatus::Processing);
        $service->saveBulkOperation($current, false);

        $job = $this->_makeJob();
        $job->callAfter();

        $reloadedCurrent = $service->getBulkOperationByShopifyGid(self::BULK_OP_GID);
        self::assertEquals(BulkOperationStatus::Completed, $reloadedCurrent->getStatus());
    }

    // -------------------------------------------------------------------------
    // Failures, run through the real queue
    // -------------------------------------------------------------------------

    /**
     * Regression coverage for craftcms/shopify#242: a failed job left its operation `processing`, which
     * blocked the sync queue for 24 hours. It should now be retried, and fail the operation once it gives up.
     */
    public function testFailingJobIsRetriedThenFailsItsOperationAndStartsTheNext(): void
    {
        $service = Plugin::getInstance()->getBulkOperations();
        $this->_saveProcessingOperation();
        $nextId = $this->_saveQueuedOperation('query { next }');
        $sentQueries = $this->_stubBulkOperationStart();

        // Every download attempt fails
        $this->_mockDataFileResponses(array_fill(0, ProcessBulkOperationData::MAX_ATTEMPTS, new Response(500)));

        $jobId = QueueHelper::push($this->_makeQueueJob());

        for ($attempt = 1; $attempt < ProcessBulkOperationData::MAX_ATTEMPTS; $attempt++) {
            $this->_executeJobAfterTtr($jobId);

            self::assertFalse($this->_jobRow($jobId)['fail'], "Attempt $attempt should be retried, not failed.");
            self::assertEquals(BulkOperationStatus::Processing, $service->getBulkOperationByShopifyGid(self::BULK_OP_GID)->getStatus());
            self::assertSame([], $sentQueries->getArrayCopy(), 'The next operation should wait while this one is being retried.');
        }

        $this->_executeJobAfterTtr($jobId);

        self::assertTrue($this->_jobRow($jobId)['fail'], 'The job should fail after its last attempt.');
        self::assertEquals(BulkOperationStatus::Failed, $service->getBulkOperationByShopifyGid(self::BULK_OP_GID)->getStatus());
        self::assertSame(['query { next }'], $sentQueries->getArrayCopy(), 'The next operation should have been sent to Shopify.');
        self::assertEquals(BulkOperationStatus::Created, $service->getAllBulkOperations()->firstWhere('id', $nextId)->getStatus());
    }

    public function testRetriedJobCompletesItsOperation(): void
    {
        $service = Plugin::getInstance()->getBulkOperations();
        $this->_saveProcessingOperation();
        $this->_stubBulkOperationStart();

        Plugin::getInstance()->set('products', $this->makeEmpty(Products::class, [
            'createOrUpdateProduct' => true,
        ]));

        // The first download fails, the retry succeeds
        $this->_mockDataFileResponses([new Response(500), new Response(200, [], file_get_contents($this->_fixturePath()))]);

        $jobId = QueueHelper::push($this->_makeQueueJob('all'));
        $this->_executeJobAfterTtr($jobId);
        $this->_executeJobAfterTtr($jobId);

        self::assertNull($this->_jobRow($jobId), 'The job should have finished.');
        self::assertEquals(BulkOperationStatus::Completed, $service->getBulkOperationByShopifyGid(self::BULK_OP_GID)->getStatus());
        self::assertEquals(60, ShopifyData::find()->count());
    }

    /**
     * A worker that is killed (e.g. by a time or memory limit) never reports an error. When the job is reserved
     * again after its last attempt, yii2-queue 2.3.8+ drops it, and older versions (allowed by Craft 4) run it
     * once more. Either way, the operation should fail and the job shouldn’t be left waiting.
     */
    public function testJobDroppedAfterItsWorkerDiedFailsItsOperation(): void
    {
        $service = Plugin::getInstance()->getBulkOperations();
        $this->_saveProcessingOperation();
        $this->_stubBulkOperationStart();

        // If the job runs again, its download fails
        $this->_mockDataFileResponses([new Response(500)]);

        $jobId = QueueHelper::push($this->_makeQueueJob());

        // Simulate a worker that reserved the job for its last attempt and then died
        Db::update(CraftTable::QUEUE, [
            'attempt' => ProcessBulkOperationData::MAX_ATTEMPTS,
            'dateReserved' => Db::prepareDateForDb(new \DateTime()),
            'timeUpdated' => time(),
        ], ['id' => $jobId]);
        $this->_executeJobAfterTtr($jobId);

        $row = $this->_jobRow($jobId);
        self::assertTrue($row === null || $row['fail'], 'The job should have been dropped or failed.');
        self::assertEquals(BulkOperationStatus::Failed, $service->getBulkOperationByShopifyGid(self::BULK_OP_GID)->getStatus());
    }

    public function testJobReservedAgainAfterItsWorkerDiedIsRetried(): void
    {
        $service = Plugin::getInstance()->getBulkOperations();
        $this->_saveProcessingOperation();

        $this->_mockDataFileResponses([new Response(500)]);

        $jobId = QueueHelper::push($this->_makeQueueJob());

        // A worker reserved the first attempt and died; the second attempt should still run
        Db::update(CraftTable::QUEUE, [
            'attempt' => 1,
            'dateReserved' => Db::prepareDateForDb(new \DateTime()),
            'timeUpdated' => time(),
        ], ['id' => $jobId]);
        $this->_executeJobAfterTtr($jobId);

        $row = $this->_jobRow($jobId);
        self::assertNotNull($row, 'The job should still be in the queue.');
        self::assertEquals(2, $row['attempt']);
        self::assertFalse($row['fail']);
        self::assertEquals(BulkOperationStatus::Processing, $service->getBulkOperationByShopifyGid(self::BULK_OP_GID)->getStatus());
    }

    /**
     * yii2-queue older than 2.3.8 (allowed by Craft 4) runs a job reserved again after its last attempt instead of
     * dropping it. When that run succeeds and queues the next batch, the operation is still processing.
     */
    public function testSuccessfulRunAfterItsWorkerDiedOnTheLastAttemptKeepsProcessing(): void
    {
        $service = Plugin::getInstance()->getBulkOperations();
        $this->_saveProcessingOperation();
        $this->_stubBulkOperationStart();

        Plugin::getInstance()->set('products', $this->makeEmpty(Products::class, [
            'createOrUpdateProduct' => true,
        ]));
        $this->_mockDataFileResponses([new Response(200, [], file_get_contents($this->_fixturePath()))]);

        $jobId = QueueHelper::push($this->_makeQueueJob('all', batchSize: 10));

        // Simulate a worker that reserved the job for its last attempt and then died
        Db::update(CraftTable::QUEUE, [
            'attempt' => ProcessBulkOperationData::MAX_ATTEMPTS,
            'dateReserved' => Db::prepareDateForDb(new \DateTime()),
            'timeUpdated' => time(),
        ], ['id' => $jobId]);
        $this->_executeJobAfterTtr($jobId);

        if (version_compare(InstalledVersions::getVersion('yiisoft/yii2-queue'), '2.3.8', '>=')) {
            self::assertEquals(BulkOperationStatus::Failed, $service->getBulkOperationByShopifyGid(self::BULK_OP_GID)->getStatus());
            return;
        }

        self::assertEquals(BulkOperationStatus::Processing, $service->getBulkOperationByShopifyGid(self::BULK_OP_GID)->getStatus());

        $this->_runQueue();

        self::assertEquals(BulkOperationStatus::Completed, $service->getBulkOperationByShopifyGid(self::BULK_OP_GID)->getStatus());
        self::assertEquals(60, ShopifyData::find()->count());
    }

    /**
     * A batch that fails partway through is retried from where it started, keeping the earlier batches’ data.
     */
    public function testBatchThatFailsPartwayIsRetriedFromItsStart(): void
    {
        $service = Plugin::getInstance()->getBulkOperations();
        $this->_saveProcessingOperation();
        $this->_stubBulkOperationStart();

        // The product is the last line read, so the first save fails after the rest of its batch is written
        $productSaves = 0;
        Plugin::getInstance()->set('products', $this->makeEmpty(Products::class, [
            'createOrUpdateProduct' => function() use (&$productSaves) {
                if (++$productSaves === 1) {
                    throw new \yii\db\Exception('Deadlock found when trying to get lock');
                }
                return true;
            },
        ]));
        $this->_mockDataFileResponses([new Response(200, [], file_get_contents($this->_fixturePath()))]);

        QueueHelper::push($this->_makeQueueJob('all', batchSize: 25));
        $this->_runQueue();

        self::assertSame(2, $productSaves);
        self::assertEquals(BulkOperationStatus::Completed, $service->getBulkOperationByShopifyGid(self::BULK_OP_GID)->getStatus());
        self::assertEquals(60, ShopifyData::find()->count());
        self::assertEquals(58, ShopifyData::find()->where(['parentId' => 'gid://shopify/Product/6656149192755'])->count());
        self::assertFalse((new Query())->from(CraftTable::QUEUE)->exists(), 'No jobs should be left or failed.');
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function _fixturePath(): string
    {
        return __DIR__ . '/../../fixtures/data/bulk-operation-single-product.jsonl';
    }

    private function _copyFixtureToTempFile(): string
    {
        $filePath = Assets::tempFilePath('jsonl');
        copy($this->_fixturePath(), $filePath);

        return $filePath;
    }

    private function _makeExecutableJob(string $filePath, int $objectCount, string $clearData, int $batchSize = 100): ProcessBulkOperationData
    {
        $bulkOperation = new BulkOperation();
        $bulkOperation->shopifyGid = self::BULK_OP_GID;
        $bulkOperation->query = 'query {}';
        $bulkOperation->clearData = $clearData;
        $bulkOperation->setStatus(BulkOperationStatus::Processing);
        Plugin::getInstance()->getBulkOperations()->saveBulkOperation($bulkOperation, false);

        return new ProcessBulkOperationData([
            'bulkOperationShopifyGid' => self::BULK_OP_GID,
            'dataUrl' => 'https://storage.example.com/data.jsonl',
            'objectCount' => $objectCount,
            'clearData' => $clearData,
            'tempFilePath' => $filePath,
            'batchSize' => $batchSize,
        ]);
    }

    /**
     * Executes the job and every batch it spawns, serializing each batch through the queue as a real worker would.
     */
    private function _runJob(ProcessBulkOperationData $job): void
    {
        $queue = new RecordingQueue();
        $job->execute($queue);

        while ($next = $queue->popJob()) {
            $next->execute($queue);
        }
    }

    private function _saveProcessingOperation(): void
    {
        $bulkOperation = new BulkOperation();
        $bulkOperation->shopifyGid = self::BULK_OP_GID;
        $bulkOperation->query = 'query {}';
        $bulkOperation->clearData = 'none';
        $bulkOperation->url = 'https://storage.example.com/data.jsonl';
        $bulkOperation->objectCount = 60;
        $bulkOperation->shopifyStatus = 'COMPLETED';
        $bulkOperation->setStatus(BulkOperationStatus::Processing);
        Plugin::getInstance()->getBulkOperations()->saveBulkOperation($bulkOperation, false);
    }

    private function _makeQueueJob(string $clearData = 'none', int $batchSize = 100): ProcessBulkOperationData
    {
        return new ProcessBulkOperationData([
            'bulkOperationShopifyGid' => self::BULK_OP_GID,
            'dataUrl' => 'https://storage.example.com/data.jsonl',
            'objectCount' => 60,
            'clearData' => $clearData,
            'batchSize' => $batchSize,
        ]);
    }

    /**
     * Runs every job in the queue until none are left, releasing jobs held for a retry, as a worker would.
     */
    private function _runQueue(): void
    {
        for ($i = 0; $i < 50; $i++) {
            Db::update(CraftTable::QUEUE, ['dateReserved' => null, 'timeUpdated' => null], ['fail' => false]);

            if (!Craft::$app->getQueue()->executeJob()) {
                return;
            }
        }

        self::fail('The queue didn’t finish.');
    }

    private function _saveQueuedOperation(string $query): int
    {
        $bulkOperation = new BulkOperation();
        $bulkOperation->query = $query;
        $bulkOperation->clearData = 'none';
        $bulkOperation->setStatus(BulkOperationStatus::Queued);
        Plugin::getInstance()->getBulkOperations()->saveBulkOperation($bulkOperation, false);

        return $bulkOperation->id;
    }

    /**
     * Stubs the Shopify API so starting the next operation succeeds, recording the queries sent.
     */
    private function _stubBulkOperationStart(): \ArrayObject
    {
        $sentQueries = new \ArrayObject();
        Plugin::getInstance()->set('api', $this->makeEmpty(Api::class, [
            'query' => function($query, $variables = null) use ($sentQueries) {
                if (is_array($variables) && array_key_exists('query', $variables)) {
                    $sentQueries[] = $variables['query'];
                    return [
                        'bulkOperation' => ['id' => 'gid://shopify/BulkOperation/next-started-001', 'status' => 'CREATED', 'type' => 'QUERY'],
                        'userErrors' => [],
                    ];
                }

                return ['edges' => []];
            },
        ]));

        return $sentQueries;
    }

    /**
     * Runs the job with the real queue. A job that is still reserved (being retried, or left by a dead worker)
     * is released first, as the queue does once its TTR has passed.
     */
    private function _executeJobAfterTtr(string $jobId): void
    {
        Db::update(CraftTable::QUEUE, ['dateReserved' => null, 'timeUpdated' => null], ['id' => $jobId, 'fail' => false]);
        Craft::$app->getQueue()->executeJob($jobId);
    }

    private function _jobRow(string $jobId): ?array
    {
        $row = (new Query())->from(CraftTable::QUEUE)->where(['id' => $jobId])->one();

        if (!$row) {
            return null;
        }

        $row['fail'] = (bool)$row['fail'];
        $row['attempt'] = (int)$row['attempt'];

        return $row;
    }

    /**
     * @param Response[] $responses
     */
    private function _mockDataFileResponses(array $responses): void
    {
        $handler = HandlerStack::create(new MockHandler($responses));

        Craft::$container->set(Client::class, function($container, array $params) use ($handler): Client {
            return new Client(['handler' => $handler] + ($params[0] ?? []));
        });
    }

    private function _mockDataFileResponse(string $body): void
    {
        $handler = HandlerStack::create(new MockHandler([new Response(200, [], $body)]));

        Craft::$container->set(Client::class, function($container, array $params) use ($handler): Client {
            return new Client(['handler' => $handler] + ($params[0] ?? []));
        });
    }

    private function _setUriFormat(string $uriFormat): void
    {
        $this->_originalUriFormat ??= Plugin::getInstance()->getSettings()->uriFormat;
        Plugin::getInstance()->getSettings()->uriFormat = $uriFormat;
    }

    private function _findProduct(string $gid): ?Product
    {
        return Product::find()->shopifyGid($gid)->status(null)->one();
    }

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

    public function callLoadData(): Batchable
    {
        return $this->loadData();
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

    public function callAfter(): void
    {
        // Call only our override's logic, not BaseBatchedJob::after() which requires queue context
        $bulkOperation = Plugin::getInstance()->getBulkOperations()->getBulkOperationByShopifyGid($this->bulkOperationShopifyGid);

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

/**
 * Records pushed jobs so the batches spawned by a batched job can be executed one after another.
 */
class RecordingQueue extends \yii\queue\Queue
{
    /**
     * @var string[]
     */
    private array $_messages = [];

    public function popJob(): ?BaseBatchedJob
    {
        $message = array_shift($this->_messages);

        return $message === null ? null : $this->serializer->unserialize($message);
    }

    public function status($id)
    {
        return self::STATUS_WAITING;
    }

    protected function pushMessage($payload, $ttr, $delay, $priority)
    {
        $this->_messages[] = $payload;

        return (string)count($this->_messages);
    }
}
