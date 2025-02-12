<?php

namespace craft\shopify\migrations;

use craft\db\Migration;
use craft\shopify\db\Table;
use craft\shopify\records\BulkOperation;

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
            'status' => $this->enum('status', [BulkOperation::STATUS_QUEUED, BulkOperation::STATUS_CREATED, BulkOperation::STATUS_PROCESSING, BulkOperation::STATUS_COMPLETED])->notNull()->defaultValue(BulkOperation::STATUS_QUEUED),
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
