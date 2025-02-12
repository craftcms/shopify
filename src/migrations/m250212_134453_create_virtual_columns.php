<?php

namespace craft\shopify\migrations;

use craft\db\Migration;
use craft\shopify\db\Table;

/**
 * m250212_134453_create_virtual_columns migration.
 */
class m250212_134453_create_virtual_columns extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp(): bool
    {
        $db = $this->getDb();
        $qb = $db->getQueryBuilder();

        $varcharColumns = [
            'createdAt' => 'createdAt',
            'handle' => 'handle',
            'productType' => 'productType',
            'publishedAt' => 'publishedAt',
            'publishedScope' => 'publishedScope',
            'status' => 'shopifyStatus',
            'templateSuffix' => 'templateSuffix',
            'title' => 'title',
            'updatedAt' => 'updatedAt',
            'vendor' => 'vendor',
        ];

        foreach ($varcharColumns as $col => $alias) {
            $this->execute("ALTER TABLE " . Table::DATA . " ADD COLUMN " .
                $db->quoteColumnName($alias) . " " . $qb->getColumnType($this->string()) . " GENERATED ALWAYS AS (" .
                $qb->jsonExtract('data', [$col]) . ") STORED;");
        }

        $textColumns = [
            'tags' => 'tags',
            'options' => 'options',
        ];

        foreach ($textColumns as $col => $alias) {
            $this->execute("ALTER TABLE " . Table::DATA . " ADD COLUMN " .
                $db->quoteColumnName($alias) . ' ' . $qb->getColumnType($this->text()) . " GENERATED ALWAYS AS (" .
                $qb->jsonExtract('data', [$col]) . ") STORED;");
        }

        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown(): bool
    {
        echo "m250212_134453_create_virtual_columns cannot be reverted.\n";
        return false;
    }
}
