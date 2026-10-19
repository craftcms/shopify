<?php

namespace craft\shopify\migrations;

use craft\db\Migration;
use craft\shopify\db\Table;

/**
 * m261016_090000_add_position_to_data_table migration.
 */
class m261016_090000_add_position_to_data_table extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp(): bool
    {
        if (!$this->db->columnExists(Table::DATA, 'position')) {
            $this->addColumn(Table::DATA, 'position', $this->integer()->after('parentId'));
        }

        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown(): bool
    {
        echo "m261016_090000_add_position_to_data_table cannot be reverted.\n";
        return false;
    }
}
