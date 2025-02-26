<?php

namespace craft\shopify\migrations;

use craft\db\Migration;
use craft\shopify\db\Table;

/**
 * m250225_090046_add_stale_column_to_shopify_data_table migration.
 */
class m250225_090046_add_stale_column_to_shopify_data_table extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp(): bool
    {
        $this->addColumn(Table::DATA, 'stale', $this->boolean()->notNull()->defaultValue(false));

        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown(): bool
    {
        echo "m250225_090046_add_stale_column_to_shopify_data_table cannot be reverted.\n";
        return false;
    }
}
