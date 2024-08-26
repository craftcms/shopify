<?php

namespace craft\shopify\migrations;

use Craft;
use craft\db\Migration;

/**
 * m240826_034149_switch_variant_to_medium_text migration.
 */
class m240826_034149_switch_variant_to_medium_text extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp(): bool
    {
        // change the shopify_productdata.variants column to medium text
        $this->alterColumn('{{%shopify_productdata}}', 'variants', $this->mediumText());

        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown(): bool
    {
        echo "m240826_034149_switch_variant_to_medium_text cannot be reverted.\n";
        return false;
    }
}
