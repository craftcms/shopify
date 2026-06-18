<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\shopify\tests\fixtures;

use craft\shopify\models\BulkOperation;
use craft\shopify\Plugin;
use yii\test\DbFixture;

/**
 * Loads bulk operation records via the BulkOperations service.
 */
class BulkOperationsFixture extends DbFixture
{
    /** @var BulkOperation[] */
    public array $data = [];

    public function load(): void
    {
        $rows = require __DIR__ . '/data/shopify-bulk-operations.php';
        $service = Plugin::getInstance()->getBulkOperations();

        foreach ($rows as $key => $row) {
            $model = new BulkOperation();
            $model->shopifyId = $row['shopifyId'];
            $model->url = $row['url'];
            $model->objectCount = (int)($row['objectCount'] ?? 0);
            $model->query = $row['query'] ?? null;
            $model->shopifyStatus = $row['shopifyStatus'] ?? null;
            $model->clearData = $row['clearData'] ?? BulkOperation::CLEAR_DATA_NONE ?? 'none';
            $model->setStatus($row['status']);

            $service->saveBulkOperation($model, false);
            $this->data[$key] = $model;
        }
    }

    public function unload(): void
    {
        $service = Plugin::getInstance()->getBulkOperations();
        foreach ($this->data as $model) {
            if ($model->id) {
                $service->deleteBulkOperationById($model->id);
            }
        }
        $this->data = [];
    }
}
