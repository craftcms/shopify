<?php

namespace craft\shopify\migrations;

use Craft;
use craft\db\Migration;
use craft\shopify\db\Table;
use yii\db\Expression;

/**
 * m250213_152850_migrate_shopify_gid_column_for_products migration.
 */
class m250213_152850_migrate_shopify_gid_column_for_products extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp(): bool
    {
        $this->update(Table::PRODUCTS, ['shopifyGid' => new Expression('CONCAT("gid://shopify/Product/", [[shopifyId]])')], updateTimestamp: false);

        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown(): bool
    {
        echo "m250213_152850_migrate_shopify_gid_column_for_products cannot be reverted.\n";
        return false;
    }
}
