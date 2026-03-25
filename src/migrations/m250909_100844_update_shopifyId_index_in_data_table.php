<?php

namespace craft\shopify\migrations;

use craft\db\Migration;
use craft\shopify\db\Table;

/**
 * m250909_100844_update_shopifyId_index_in_data_table migration.
 */
class m250909_100844_update_shopifyId_index_in_data_table extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp(): bool
    {
        $this->dropIndexIfExists(Table::DATA, ['shopifyId'], true);

        $this->createIndexIfMissing(Table::DATA, ['shopifyId'], false);

        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown(): bool
    {
        echo "m250909_100844_update_shopifyId_index_in_data_table cannot be reverted.\n";
        return false;
    }
}
