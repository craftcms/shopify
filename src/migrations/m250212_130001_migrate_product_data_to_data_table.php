<?php

namespace craft\shopify\migrations;

use craft\db\Migration;
use craft\db\Query;
use craft\helpers\Json;
use craft\shopify\db\Table;

/**
 * m250212_130001_migrate_product_data_to_data_table migration.
 */
class m250212_130001_migrate_product_data_to_data_table extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp(): bool
    {
        // Only migrate the data if the data table is empty
        if ((new Query())->from(Table::DATA)->count() > 0) {
            return true;
        }

        $batchInserts = [];

        $productData = (new Query())
            ->select('*')
            ->from('{{%shopify_productdata}}')
            ->all();

        if (empty($productData)) {
            return true;
        }

        foreach ($productData as $productDatum) {
            $id = 'gid://shopify/Product/' . $productDatum['shopifyId'];
            $batchInserts[] = [
                'shopifyId' => $id,
                'data' => Json::encode([
                    "id" => $id,
                    "tags" => explode(', ', $productDatum['tags'] ?? ''),
                    "title" => $productDatum['title'],
                    "handle" => $productDatum['handle'],
                    "status" => $productDatum['shopifyStatus'],
                    "vendor" => $productDatum['vendor'],
                    "createdAt" => $productDatum['createdAt'],
                    "updatedAt" => $productDatum['updatedAt'],
                    "productType" => $productDatum['productType'],
                    "publishedAt" => $productDatum['publishedAt'],
                    "templateSuffix" => $productDatum['templateSuffix'],
                    "descriptionHtml" => $productDatum['bodyHtml'],
                    "options" => $productDatum['options'],
                ]),
                'type' => 'Product',
                'dateCreated' => $productDatum['dateCreated'],
                'uid' => $productDatum['uid'],
            ];
        }

        foreach (array_chunk($batchInserts, 1000) as $inserts) {
            $this->batchInsert(Table::DATA, [
                'shopifyId',
                'data',
                'type',
                'dateCreated',
                'uid',
            ], $inserts);
        }

        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown(): bool
    {
        echo "m250212_130001_migrate_product_data_to_data_table cannot be reverted.\n";
        return false;
    }
}
