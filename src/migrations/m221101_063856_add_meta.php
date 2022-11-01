<?php

namespace craft\shopify\migrations;

use craft\db\Migration;
use craft\shopify\db\Table;

/**
 * m221101_063856_add_meta migration.
 */
class m221101_063856_add_meta extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp(): bool
    {
        if (!$this->db->columnExists(Table::PRODUCTDATA, 'metaFields')) {
            $this->addColumn(Table::PRODUCTDATA, 'metaFields', $this->text()->after('vendor'));
        }

        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown(): bool
    {
        echo "m221101_063856_add_meta cannot be reverted.\n";
        return false;
    }
}
