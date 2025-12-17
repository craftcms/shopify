<?php

namespace craft\shopify\migrations;

use craft\db\Migration;
use craft\shopify\db\Table;

/**
 * m250808_100206_update_shopify_product_indexes migration.
 */
class m250808_100206_update_shopify_product_indexes extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp(): bool
    {
        $this->dropIndexIfExists(Table::PRODUCTS, ['shopifyId'], true);
        $this->dropIndexIfExists(Table::PRODUCTS, ['shopifyGid'], true);

        $this->createIndexIfMissing(Table::PRODUCTS, ['shopifyId']);
        $this->createIndexIfMissing(Table::PRODUCTS, ['shopifyGid']);

        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown(): bool
    {
        echo "m250808_100206_update_shopify_product_indexes cannot be reverted.\n";
        return false;
    }
}
