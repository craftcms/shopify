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

        // Need to do a "table swap" to create the `id` column as a primary key
        // This is because in some engines (like MySQL) certain settings do not allow tables without a primary key, even temporarily.
        $tempTable = '{{%shopify_data_new}}';

        $this->createTable($tempTable, [
            'id' => $this->primaryKey(),
            'shopifyId' => $this->string(),
            'type' => $this->string(),
            'data' => $this->json(),
            'parentId' => $this->string(),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid(),
        ]);

        $this->createIndex(null, $tempTable, ['shopifyId'], true);
        $this->createIndex(null, $tempTable, ['parentId'], false);

        // Copy data from the old table to the new temporary table
        $this->execute('INSERT INTO ' . $tempTable . ' ([[shopifyId]], [[type]], [[data]], [[parentId]], [[dateCreated]], [[dateUpdated]], [[uid]]) SELECT [[shopifyId]], [[type]], [[data]], [[parentId]], [[dateCreated]], [[dateUpdated]], [[uid]] FROM ' . Table::DATA);

        // Drop the old table
        $this->dropTable(Table::DATA);

        // Rename the temporary table to the original table name
        $this->renameTable($tempTable, Table::DATA);

        // Recreate generated columns
        $installMigration = new Install();
        $installMigration->createGeneratedColumns();

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
