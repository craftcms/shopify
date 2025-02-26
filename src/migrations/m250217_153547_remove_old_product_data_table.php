<?php

namespace craft\shopify\migrations;

use craft\db\Migration;

/**
 * m250217_153547_remove_old_product_data_table migration.
 */
class m250217_153547_remove_old_product_data_table extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp(): bool
    {
        $this->dropAllForeignKeysToTable('{{%shopify_productdata}}');
        $this->dropTableIfExists('{{%shopify_productdata}}');

        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown(): bool
    {
        echo "m250217_153547_remove_old_product_data_table cannot be reverted.\n";
        return false;
    }
}
