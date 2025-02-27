<?php

namespace craft\shopify\migrations;

use Craft;
use craft\db\Migration;
use craft\shopify\db\Table;

/**
 * m250227_090938_fix_products_data_foreign_key migration.
 */
class m250227_090938_fix_products_data_foreign_key extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp(): bool
    {
        $this->dropForeignKeyIfExists(Table::PRODUCTS, ['shopifyGid']);

        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown(): bool
    {
        echo "m250227_090938_fix_products_data_foreign_key cannot be reverted.\n";
        return false;
    }
}
