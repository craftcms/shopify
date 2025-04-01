<?php

namespace craft\shopify\migrations;

use Craft;
use craft\db\Migration;
use craft\shopify\db\Table;

/**
 * m250401_073039_fix_shopify_gid_fk migration.
 */
class m250401_073039_fix_shopify_gid_fk extends Migration
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
        echo "m250401_073039_fix_shopify_gid_fk cannot be reverted.\n";
        return false;
    }
}
