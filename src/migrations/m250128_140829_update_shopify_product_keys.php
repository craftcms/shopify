<?php

namespace craft\shopify\migrations;

use craft\db\Migration;
use craft\shopify\db\Table;
use yii\db\Expression;

/**
 * m250128_140829_update_shopify_product_keys migration.
 */
class m250128_140829_update_shopify_product_keys extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp(): bool
    {
        $this->dropForeignKeyIfExists(Table::PRODUCTS, ['shopifyId']);

        // update the `shopify_products` table, prefix the `shopifyId` column values with `gid://shopify/Product/`
        $this->update(Table::PRODUCTS, [
            'shopifyId' => new Expression('CONCAT("gid://shopify/Product/", [[shopifyId]])'),
        ]);

        // $this->addForeignKey(null, Table::PRODUCTS, ['shopifyId'], Table::DATA, ['shopifyId'], null, null);

        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown(): bool
    {
        echo "m250128_140829_update_shopify_product_keys cannot be reverted.\n";
        return false;
    }
}
