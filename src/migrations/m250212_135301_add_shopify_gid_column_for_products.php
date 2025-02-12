<?php

namespace craft\shopify\migrations;

use Craft;
use craft\db\Migration;
use craft\shopify\db\Table;

/**
 * m250212_135301_add_shopify_gid_column_for_products migration.
 */
class m250212_135301_add_shopify_gid_column_for_products extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp(): bool
    {
        if (!$this->getDb()->columnExists(Table::PRODUCTS, 'shopifyGid')) {
            $this->addColumn(Table::PRODUCTS, 'shopifyGid', $this->string()->after('shopifyId'));
        }

        $this->dropForeignKeyIfExists(Table::PRODUCTS, ['shopifyId']);
        $this->createIndex(null, Table::PRODUCTS, ['shopifyGid'], true);
        $this->addForeignKey(null, Table::PRODUCTS, ['shopifyGid'], Table::DATA, ['shopifyId'], null, null);

        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown(): bool
    {
        echo "m250212_135301_add_shopify_gid_column_for_products cannot be reverted.\n";
        return false;
    }
}
