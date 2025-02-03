<?php

namespace craft\shopify\jobs;

use Craft;
use craft\base\Batchable;
use craft\helpers\Assets;
use craft\helpers\FileHelper;
use craft\queue\BaseBatchedJob;
use craft\shopify\api\BulkDataBatcher;
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
    public string $dataUrl;

    public int $totalObjects = 0;

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
        // Download the data to temporary file from the `$dataUrl`
        $filePath = Assets::tempFilePath('jsonl');

        // Retrieve remote file contents
        $client = Craft::createGuzzleClient();
        $response = $client->get($this->dataUrl);

        // Write the contents to the temporary file
        FileHelper::writeToFile($filePath, $response->getBody()->getContents());

        $bulkDataBatcher = new BulkDataBatcher();
        $bulkDataBatcher->filePath = $filePath;
        $bulkDataBatcher->total = $this->totalObjects;

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

        $record = ShopifyData::findOne(['shopifyId' => $item['id']]);
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
    }

    protected function before(): void
    {
        parent::before();

        // Mark bulk op as started
    }

    protected function after(): void
    {
        parent::after();

        // Mark bulk op as completed
    }
}
