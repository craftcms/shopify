<?php

namespace craft\shopify\jobs;

use Craft;
use craft\base\Batchable;
use craft\helpers\Assets;
use craft\helpers\FileHelper;
use craft\queue\BaseBatchedJob;
use craft\shopify\api\BulkDataBatcher;
use craft\shopify\enums\BulkOperationStatus;
use craft\shopify\Plugin;
use craft\shopify\records\BulkOperation as BulkOperationRecord;
use craft\shopify\records\ShopifyData;

/**
 * Process the data from the completed bulk operation.
 *
 * @since 6.0.0
 */
class ProcessBulkOperationData extends BaseBatchedJob
{
    /**
     * @var string
     */
    public string $bulkOperationShopifyId;

    /**
     * @var string
     */
    public string $dataUrl;

    /**
     * @var int
     */
    public int $objectCount = 0;

    /**
     * @var string|null
     */
    public ?string $tempFilePath = null;

    /**
     * Signal which data should be cleared before processing the bulk operation.
     * This is either `none`, `all` or a gid string.
     *
     * @var string
     */
    public string $clearData = BulkOperationRecord::CLEAR_DATA_NONE;

    /**
     * @inheritdoc
     */
    protected function defaultDescription(): ?string
    {
        return Craft::t('shopify', 'Processing bulk operation data');
    }

    /**
     * @inheritdoc
     */
    protected function loadData(): Batchable
    {
        // The first iteration of the batch we will not have a file name yet, so we will generate one here and store it
        // on the job context so that it is serialized and available for subsequent iterations.
        if ($this->tempFilePath === null) {
            $this->tempFilePath = Assets::tempFilePath('jsonl');
        }

        $client = Craft::createGuzzleClient();

        $response = $client->get($this->dataUrl);

        FileHelper::writeToFile($this->tempFilePath, $response->getBody()->getContents());

        $bulkDataBatcher = new BulkDataBatcher();

        $bulkDataBatcher->filePath = $this->tempFilePath;

        $bulkDataBatcher->total = $this->objectCount;

        return $bulkDataBatcher;
    }

    /**
     * @inheritdoc
     */
    protected function processItem(mixed $item): void
    {
        $json = $item;
        $item = json_decode($json, true);

        if ($item === null || !isset($item['id'])) {
            return;
        }

        $parentId = $item['__parentId'] ?? null;

        // Find data records based on their Shopify ID and parent ID. This is to avoid overwriting
        // records with the same ID but different parents (e.g. Metafields, Images etc).
        $record = ShopifyData::findOne(['shopifyId' => $item['id'], 'parentId' => $parentId]);
        if (!$record) {
            $record = new ShopifyData();
        }

        // Parse Shopify GraphQl ID to get type.
        $parts = explode('/', str_replace('gid://shopify/', '', $item['id']));
        $type = $parts[0];

        $record->shopifyId = $item['id'];
        $record->type = $type;
        $record->data = $item;
        $record->parentId = $item['__parentId'] ?? null;
        $record->save();

        // Process the data based on the type
        if ($record->type === 'Product') {
            Plugin::getInstance()->getProducts()->createOrUpdateProduct($item);
        }

        // Always delete the temporary file after processing the current job. We don't know if the next chunk of the
        // job will be picked up by the same worker or not. If we leave the file there, it effectively means that
        // the file has leaked and we won't be able to delete it from all worker servers once the batch is done
        if ($this->tempFilePath !== null && file_exists($this->tempFilePath)) {
            FileHelper::unlink($this->tempFilePath);
        }
    }

    /**
     * @inheritdoc
     */
    protected function before(): void
    {
        parent::before();

        // Make sure bulk op is marked as processing
        $bulkOperation = Plugin::getInstance()->getBulkOperations()->getBulkOperationByShopifyId($this->bulkOperationShopifyId);

        if (!$bulkOperation) {
            return;
        }

        $bulkOperation->setStatus(BulkOperationStatus::Processing);

        Plugin::getInstance()->getBulkOperations()->saveBulkOperation($bulkOperation, false);

        // Clear data if requested
        if ($this->clearData === BulkOperationRecord::CLEAR_DATA_ALL) {
            ShopifyData::deleteAll();
        } elseif ($this->clearData !== BulkOperationRecord::CLEAR_DATA_NONE) {
            Plugin::getInstance()->getProducts()->deleteShopifyDataByShopifyId($this->clearData);
        }
    }

    /**
     * @inheritdoc
     */
    protected function after(): void
    {
        parent::after();

        // Mark bulk op as completed
        $bulkOperation = Plugin::getInstance()->getBulkOperations()->getBulkOperationByShopifyId($this->bulkOperationShopifyId);

        if (!$bulkOperation) {
            return;
        }

        $bulkOperation->setStatus(BulkOperationStatus::Completed);

        Plugin::getInstance()->getBulkOperations()->saveBulkOperation($bulkOperation, false);

        // Start the next bulk op if there is one
        Plugin::getInstance()->getBulkOperations()->nextBulkOperation();
    }
}
