<?php

namespace craft\shopify\migrations;

use craft\db\Migration;
use craft\shopify\db\Table;

/**
 * m260617_100001_rename_shopifyId_to_shopifyGid_in_bulk_operations_table migration.
 */
class m260617_100001_rename_shopifyId_to_shopifyGid_in_bulk_operations_table extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp(): bool
    {
        if ($this->db->columnExists(Table::BULK_OPERATIONS, 'shopifyGid')) {
            return true;
        }

        $this->renameColumn(Table::BULK_OPERATIONS, 'shopifyId', 'shopifyGid');

        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown(): bool
    {
        echo "m260617_100001_rename_shopifyId_to_shopifyGid_in_bulk_operations_table cannot be reverted.\n";
        return false;
    }
}
