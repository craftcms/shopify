<?php

namespace craft\shopify\migrations;

use craft\db\Migration;
use craft\shopify\db\Table;

/**
 * m250127_121804_create_shopify_data_table migration.
 */
class m250127_121804_create_shopify_data_table extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp(): bool
    {
        $this->archiveTableIfExists(Table::DATA);
        $this->createTable(Table::DATA, [
            'shopifyId' => $this->string(),
            'type' => $this->string(),
            'data' => $this->json(),
            'parentId' => $this->string(),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid(),
            'PRIMARY KEY([[shopifyId]])',
        ]);

        $this->createIndex(null, Table::DATA, ['shopifyId'], true);
        $this->createIndex(null, Table::DATA, ['parentId'], false);

        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown(): bool
    {
        echo "m250127_121804_create_shopify_data_table cannot be reverted.\n";
        return false;
    }
}
