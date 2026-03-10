<?php

namespace craft\shopify\migrations;

use craft\db\Migration;
use craft\shopify\db\Table;

/**
 * m260309_182454_remove_publishedOnCurrentPublication_column migration.
 */
class m260309_182454_remove_publishedOnCurrentPublication_column extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp(): bool
    {
        $this->dropColumn(Table::DATA, 'publishedOnCurrentPublication');

        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown(): bool
    {
        echo "m260309_182454_remove_publishedOnCurrentPublication_column cannot be reverted.\n";
        return false;
    }
}
