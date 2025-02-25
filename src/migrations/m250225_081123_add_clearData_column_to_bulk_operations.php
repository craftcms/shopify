<?php

namespace craft\shopify\migrations;

use craft\db\Migration;
use craft\shopify\db\Table;
use craft\shopify\records\BulkOperation as BulkOperationRecord;

/**
 * m250225_081123_add_clearData_column_to_bulk_operations migration.
 */
class m250225_081123_add_clearData_column_to_bulk_operations extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp(): bool
    {
        $this->addColumn(Table::BULK_OPERATIONS, 'clearData', $this->string()->notNull()->defaultValue(BulkOperationRecord::CLEAR_DATA_NONE));

        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown(): bool
    {
        echo "m250225_081123_add_clearData_column_to_bulk_operations cannot be reverted.\n";
        return false;
    }
}
