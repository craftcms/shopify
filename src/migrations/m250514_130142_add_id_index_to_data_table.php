<?php

namespace craft\shopify\migrations;

use craft\db\Migration;
use craft\shopify\db\Table;

/**
 * m250514_130142_add_id_index_to_data_table migration.
 */
class m250514_130142_add_id_index_to_data_table extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp(): bool
    {
        if ($this->getDb()->columnExists(Table::DATA, 'id')) {
            return true;
        }

        $this->execute('SET SESSION sql_require_primary_key=0');

        if ($this->getDb()->getIsPgsql()) {
            $this->dropPrimaryKey('shopify_data_pkey', Table::DATA);
        } else {
            $this->dropPrimaryKey('PRIMARY', Table::DATA);
        }

        $this->addColumn(Table::DATA, 'id', $this->primaryKey());

        $this->execute('SET SESSION sql_require_primary_key=1');
        
        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown(): bool
    {
        echo "m250514_130142_add_id_index_to_data_table cannot be reverted.\n";
        return false;
    }
}
