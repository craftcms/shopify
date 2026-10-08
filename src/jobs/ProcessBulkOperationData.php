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
    public string $bulkOperationShopifyGid = '';

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
     * The byte position in the data file that the next batch should read back from.
     *
     * @var int|null
     * @see BulkDataBatcher::$reversePosition
     * @since 8.2.0
     */
    public ?int $dataFilePosition = null;

    /**
     * @var int The item offset the current batch started at
     */
    private int $_batchItemOffset = 0;

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
        // check to see if the file is still in temporary storage
        if ($this->tempFilePath === null) {
            // Make sure we only create one temp filename
            $this->tempFilePath = Assets::tempFilePath('jsonl');
        }

        $fileExists = file_exists($this->tempFilePath);
        $fileSize = $fileExists ? filesize($this->tempFilePath) : 0;

        // If somehow a zero-byte file exists, remove it to force a re-download
        if ($fileExists && $fileSize === 0) {
            FileHelper::unlink($this->tempFilePath);
            $fileExists = false;
        }

        // Only re-download if the file doesn't already exist
        // If the queue is using multiple workers and the file leaks between them,
        if (!$fileExists) {
            $this->_downloadDataFile();
        }

        $bulkDataBatcher = new BulkDataBatcher();
        $bulkDataBatcher->filePath = $this->tempFilePath;
        $bulkDataBatcher->total = $this->objectCount;
        $bulkDataBatcher->reverse = true;
        $bulkDataBatcher->reversePosition = $this->dataFilePosition;

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
        $record = ShopifyData::findOne(['shopifyGid' => $item['id'], 'parentId' => $parentId]);
        if (!$record) {
            $record = new ShopifyData();
        }

        // Parse Shopify GraphQl ID to get type.
        $parts = explode('/', str_replace('gid://shopify/', '', $item['id']));
        $type = $parts[0];

        $record->shopifyGid = $item['id'];
        $record->type = $type;
        $record->data = $item;
        $record->parentId = $item['__parentId'] ?? null;
        $record->save();

        // Process the data based on the type
        if ($record->type === 'Product') {
            Plugin::getInstance()->getProducts()->createOrUpdateProduct($item);
        }
    }

    /**
     * @inheritdoc
     */
    protected function before(): void
    {
        parent::before();

        // Make sure bulk op is marked as processing
        $bulkOperation = Plugin::getInstance()->getBulkOperations()->getBulkOperationByShopifyGid($this->bulkOperationShopifyGid);

        if (!$bulkOperation) {
            return;
        }

        $bulkOperation->setStatus(BulkOperationStatus::Processing);

        Plugin::getInstance()->getBulkOperations()->saveBulkOperation($bulkOperation, false);

        // Clear data if requested
        if ($this->clearData === BulkOperationRecord::CLEAR_DATA_ALL) {
            ShopifyData::deleteAll();
        } elseif ($this->clearData !== BulkOperationRecord::CLEAR_DATA_NONE) {
            Plugin::getInstance()->getProducts()->deleteShopifyDataByShopifyGid($this->clearData);
        }
    }

    /**
     * @inheritdoc
     */
    protected function beforeBatch(): void
    {
        parent::beforeBatch();

        $this->_batchItemOffset = $this->itemOffset;
    }

    /**
     * @inheritdoc
     */
    protected function afterBatch(): void
    {
        parent::afterBatch();

        /** @var BulkDataBatcher $data */
        $data = $this->data();
        $this->dataFilePosition = $data->getReversePositionAfter($this->itemOffset - $this->_batchItemOffset);
    }

    /**
     * @inheritdoc
     */
    protected function after(): void
    {
        parent::after();

        // Mark bulk op as completed
        $bulkOperation = Plugin::getInstance()->getBulkOperations()->getBulkOperationByShopifyGid($this->bulkOperationShopifyGid);

        if (!$bulkOperation) {
            return;
        }

        $bulkOperation->setStatus(BulkOperationStatus::Completed);

        Plugin::getInstance()->getBulkOperations()->saveBulkOperation($bulkOperation, false);

        // Delete the temporary file
        if ($this->tempFilePath !== null && file_exists($this->tempFilePath)) {
            FileHelper::unlink($this->tempFilePath);
        }

        // If a bulk op is already `created` (with its data URL populated), send it to the queue for processing.
        // This can happen if it finished on Shopify’s end while another op was still `processing` locally.
        Plugin::getInstance()->getBulkOperations()->queueNextBulkOperation();

        // Otherwise, start the next queued bulk op on Shopify if there is one
        Plugin::getInstance()->getBulkOperations()->nextBulkOperation();
    }

    /**
     * Streams the remote data file to disk, only moving it into place once the download has completed.
     *
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    private function _downloadDataFile(): void
    {
        $downloadPath = $this->tempFilePath . '.download';

        try {
            Craft::createGuzzleClient()->get($this->dataUrl, ['sink' => $downloadPath]);
            rename($downloadPath, $this->tempFilePath);
        } finally {
            if (file_exists($downloadPath)) {
                FileHelper::unlink($downloadPath);
            }
        }
    }
}
