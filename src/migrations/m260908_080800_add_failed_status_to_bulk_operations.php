<?php

namespace craft\shopify\migrations;

use craft\db\Migration;
use craft\shopify\db\Table;
use craft\shopify\enums\BulkOperationStatus;

/**
 * m260908_080800_add_failed_status_to_bulk_operations migration.
 */
class m260908_080800_add_failed_status_to_bulk_operations extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp(): bool
    {
        $values = [
            BulkOperationStatus::Queued->value,
            BulkOperationStatus::Created->value,
            BulkOperationStatus::Processing->value,
            BulkOperationStatus::Completed->value,
            BulkOperationStatus::Failed->value,
        ];

        if ($this->db->getIsPgsql()) {
            // Postgres implements `enum()` as a `varchar` column with a `CHECK` constraint (there's no
            // native enum type in play here), so the existing constraint has to be dropped before a new
            // one can be added for the expanded value list.
            $checks = $this->db->getSchema()->getTableChecks(Table::BULK_OPERATIONS);
            foreach ($checks as $check) {
                if (in_array('status', $check->columnNames, true)) {
                    $this->dropCheck($check->name, Table::BULK_OPERATIONS);
                }
            }
        }

        $this->alterColumn(
            Table::BULK_OPERATIONS,
            'status',
            $this->enum('status', $values)->notNull()->defaultValue(BulkOperationStatus::Queued->value),
        );

        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown(): bool
    {
        echo "m260908_080800_add_failed_status_to_bulk_operations cannot be reverted.\n";
        return false;
    }
}
