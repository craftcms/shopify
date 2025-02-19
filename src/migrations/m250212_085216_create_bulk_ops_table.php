<?php

namespace craft\shopify\migrations;

use craft\db\Migration;
use craft\shopify\db\Table;
use craft\shopify\enums\BulkOperationStatus;

/**
 * m250212_085216_create_bulk_ops_table migration.
 */
class m250212_085216_create_bulk_ops_table extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp(): bool
    {
        $this->archiveTableIfExists(Table::BULK_OPERATIONS);
        $this->createTable(Table::BULK_OPERATIONS, [
            'id' => $this->primaryKey(),
            'shopifyId' => $this->string(),
            'url' => $this->text(),
            'objectCount' => $this->integer(),
            'query' => $this->text(),
            'status' => $this->enum('status', [BulkOperationStatus::Queued->value, BulkOperationStatus::Created->value, BulkOperationStatus::Processing->value, BulkOperationStatus::Completed->value])->notNull()->defaultValue(BulkOperationStatus::Queued),
            'shopifyStatus' => $this->string(),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid(),
        ]);

        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown(): bool
    {
        echo "m250212_085216_create_bulk_ops_table cannot be reverted.\n";
        return false;
    }
}
