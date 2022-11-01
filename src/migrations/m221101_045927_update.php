<?php

namespace craft\shopify\migrations;

use craft\db\Migration;
use craft\db\Table as CraftTable;
use craft\shopify\db\Table;

/**
 * m221101_045927_update migration.
 */
class m221101_045927_update extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp(): bool
    {
        // if table exists
        if (!$this->db->tableExists(Table::PRODUCTS)) {
            // add columns
            $this->createTable(Table::PRODUCTS, [
                'id' => $this->integer()->notNull(),
                'shopifyId' => $this->string(),
                'dateCreated' => $this->dateTime()->notNull(),
                'dateUpdated' => $this->dateTime()->notNull(),
                'uid' => $this->uid(),
                'PRIMARY KEY(id)',
            ]);
        }

        if (!$this->db->tableExists(Table::PRODUCTDATA)) {
            $this->createTable(Table::PRODUCTDATA, [
                'shopifyId' => $this->string(),
                'title' => $this->text(),
                'bodyHtml' => $this->text(),
                'createdAt' => $this->dateTime(),
                'handle' => $this->string(),
                'images' => $this->text(),
                'options' => $this->text(),
                'productType' => $this->string(),
                'publishedAt' => $this->dateTime(),
                'publishedScope' => $this->string(),
                'shopifyStatus' => $this->string(),
                'tags' => $this->string(),
                'templateSuffix' => $this->string(),
                'updatedAt' => $this->string(),
                'variants' => $this->text(),
                'vendor' => $this->string(),
                'metaFields' => $this->text(),
                'dateCreated' => $this->dateTime()->notNull(),
                'dateUpdated' => $this->dateTime()->notNull(),
                'uid' => $this->string(),
                'PRIMARY KEY(shopifyId)',
            ]);

            $this->createIndex(null, Table::PRODUCTDATA, ['shopifyId'], true);
            $this->addForeignKey(null, Table::PRODUCTS, ['shopifyId'], Table::PRODUCTDATA, ['shopifyId'], 'CASCADE', 'CASCADE');
            $this->addForeignKey(null, Table::PRODUCTS, ['id'], CraftTable::ELEMENTS, ['id'], 'CASCADE', 'CASCADE');
        }

        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown(): bool
    {
        echo "m221101_045927_update cannot be reverted.\n";
        return false;
    }
}
