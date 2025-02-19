<?php

namespace craft\shopify\migrations;

use craft\db\Migration;

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
        if (!$this->db->columnExists('{{%shopify_productdata}}', 'metaFields')) {
            $this->addColumn('{{%shopify_productdata}}', 'metaFields', $this->text()->after('vendor'));
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
