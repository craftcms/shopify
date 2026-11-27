<?php

declare(strict_types=1);

namespace CraftCms\Shopify\BulkOperation\Jobs;

use craft\shopify\enums\BulkOperationStatus;
use CraftCms\Cms\Asset\AssetsHelper;
use CraftCms\Cms\Queue\BatchedJob;
use CraftCms\Cms\Queue\Contracts\Batchable;
use CraftCms\Cms\Support\Facades\I18N;
use CraftCms\Shopify\Api\Models\ShopifyData;
use CraftCms\Shopify\BulkOperation\BulkDataBatcher;
use CraftCms\Shopify\BulkOperation\BulkOperations;
use CraftCms\Shopify\BulkOperation\Models\BulkOperation as BulkOperationRecord;
use CraftCms\Shopify\Product\Products;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Override;

/**
 * Processes the data from a completed bulk operation.
 */
class ProcessBulkOperationData extends BatchedJob
{
    /**
     * Where the data file is kept between batches. Set when the first batch downloads it.
     */
    public ?string $tempFilePath = null;

    /**
     * The byte position in the data file that the next batch should read back from.
     *
     * @see BulkDataBatcher::$position
     */
    public ?int $dataFilePosition = null;

    /**
     * @param string $clearData Which data should be cleared before processing: `none`, `all` or a Shopify GID.
     */
    public function __construct(
        public readonly string $bulkOperationShopifyGid,
        public readonly string $dataUrl,
        public readonly int $objectCount = 0,
        public readonly string $clearData = BulkOperationRecord::CLEAR_DATA_NONE,
    ) {
        parent::__construct();
    }

    #[Override]
    public function handle(): void
    {
        $this->ensureDataFile();

        parent::handle();
    }

    #[Override]
    protected function loadData(): Batchable
    {
        // The path is empty until the first batch downloads the file, but the job is counted before that.
        return new BulkDataBatcher($this->tempFilePath ?? '', $this->objectCount, $this->dataFilePosition);
    }

    #[Override]
    protected function defaultDescription(): string
    {
        return I18N::prep('Processing bulk operation data', category: 'shopify');
    }

    /**
     * Marks the bulk operation as processing and clears the requested data, before the first batch.
     */
    #[Override]
    protected function before(): void
    {
        $bulkOperations = app(BulkOperations::class);
        $bulkOperation = $bulkOperations->getBulkOperationByShopifyGid($this->bulkOperationShopifyGid);

        if (!$bulkOperation) {
            return;
        }

        $bulkOperation->setStatus(BulkOperationStatus::Processing);
        $bulkOperations->saveBulkOperation($bulkOperation, false);

        if ($this->clearData === BulkOperationRecord::CLEAR_DATA_ALL) {
            ShopifyData::query()->delete();
        } elseif ($this->clearData !== BulkOperationRecord::CLEAR_DATA_NONE) {
            app(Products::class)->deleteShopifyDataByShopifyGid($this->clearData);
        }
    }

    /**
     * Saves one object from the data file, and the product element if it’s a product.
     */
    #[Override]
    protected function processItem(mixed $item): void
    {
        if (!is_array($item) || !isset($item['id'])) {
            return;
        }

        $parentId = $item['__parentId'] ?? null;

        // Find data records based on their Shopify ID and parent ID. This is to avoid overwriting
        // records with the same ID but different parents (e.g. Metafields, Images etc).
        $record = ShopifyData::query()->where('shopifyGid', $item['id'])->where('parentId', $parentId)->first()
            ?? new ShopifyData();

        // Parse Shopify GraphQl ID to get type.
        $type = explode('/', str_replace('gid://shopify/', '', $item['id']))[0];

        $record->shopifyGid = $item['id'];
        $record->type = $type;
        $record->data = $item;
        $record->parentId = $parentId;
        $record->position = $this->objectCount > 0 ? $this->objectCount - 1 - $this->itemOffset : null;
        $record->save();

        if ($type === 'Product') {
            app(Products::class)->createOrUpdateProduct($item);
        }
    }

    /**
     * Saves where the next batch should read back from.
     */
    #[Override]
    protected function afterBatch(): void
    {
        /** @var BulkDataBatcher $data */
        $data = $this->data();
        $this->dataFilePosition = $data->position;
    }

    /**
     * Marks the bulk operation as completed and starts the next one, after the last batch.
     */
    #[Override]
    protected function after(): void
    {
        if ($this->tempFilePath !== null) {
            File::delete($this->tempFilePath);
        }

        $bulkOperations = app(BulkOperations::class);
        $bulkOperation = $bulkOperations->getBulkOperationByShopifyGid($this->bulkOperationShopifyGid);

        if (!$bulkOperation) {
            return;
        }

        $bulkOperation->setStatus(BulkOperationStatus::Completed);
        $bulkOperations->saveBulkOperation($bulkOperation, false);

        // If a bulk op is already `created` (with its data URL populated), send it to the queue for processing.
        // This can happen if it finished on Shopify’s end while another op was still `processing` locally.
        $bulkOperations->queueNextBulkOperation();

        // Otherwise, start the next queued bulk op on Shopify if there is one
        $bulkOperations->nextBulkOperation();
    }

    /**
     * Makes sure the data file is in temporary storage, downloading it if needed.
     *
     * This only happens when the job runs, so a failed download is recorded as a job failure.
     */
    private function ensureDataFile(): void
    {
        $this->tempFilePath ??= AssetsHelper::tempFilePath('jsonl');

        if (File::exists($this->tempFilePath) && File::size($this->tempFilePath) > 0) {
            return;
        }

        File::delete($this->tempFilePath);

        // Download to a separate file, and only move it into place once the download has completed.
        $downloadPath = $this->tempFilePath . '.download';
        File::ensureDirectoryExists(dirname($downloadPath));

        try {
            Http::sink($downloadPath)->timeout($this->timeout)->throw()->get($this->dataUrl);
            File::move($downloadPath, $this->tempFilePath);
        } finally {
            File::delete($downloadPath);
        }
    }
}
