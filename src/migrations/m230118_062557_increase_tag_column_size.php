<?php

namespace craft\shopify\migrations;

use craft\db\Migration;

/**
 * m230118_062557_increase_tag_column_size migration.
 */
class m230118_062557_increase_tag_column_size extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp(): bool
    {
        $this->alterColumn('{{%shopify_productdata}}', 'tags', $this->text());

        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown(): bool
    {
        echo "m230118_062557_increase_tag_column_size cannot be reverted.\n";
        return false;
    }
}
