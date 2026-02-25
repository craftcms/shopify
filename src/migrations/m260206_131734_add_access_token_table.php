<?php

namespace craft\shopify\migrations;

use craft\db\Migration;
use craft\shopify\db\Table;

/**
 * m260206_131734_add_access_token_table migration.
 */
class m260206_131734_add_access_token_table extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp(): bool
    {
        if ($this->db->tableExists(Table::ACCESS_TOKENS)) {
            return true;
        }

        $this->createTable(Table::ACCESS_TOKENS, [
            'id' => $this->primaryKey(),
            'accessToken' => $this->string(),
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
        echo "m260206_131734_add_access_token_table cannot be reverted.\n";
        return false;
    }
}
