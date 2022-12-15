<?php

namespace craft\shopify\migrations;

use Craft;
use craft\db\Migration;
use craft\helpers\StringHelper;
use craft\shopify\Plugin;

/**
 * m221215_010047_store_field_layout_in_project_config migration.
 */
class m221215_010047_store_field_layout_in_project_config extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp(): bool
    {
        // Don't make the same config changes twice
        $projectConfig = Craft::$app->getProjectConfig();
        $schemaVersion = $projectConfig->get('plugins.shopify.schemaVersion', true);

        if (version_compare($schemaVersion, '4.0.5', '<')) {
            $fieldLayout = Plugin::getInstance()->getSettings()->getProductFieldLayout();

            $muteEvents = $projectConfig->muteEvents;
            $projectConfig->muteEvents = true;
            $uid = StringHelper::UUID();
            $fieldLayoutConfig = $fieldLayout->getConfig();
            $projectConfig->set(Plugin::PC_PATH_PRODUCT_FIELD_LAYOUTS, [$uid => $fieldLayoutConfig], 'Save the Shopify product field layout');
            $projectConfig->muteEvents = $muteEvents;
        }

        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown(): bool
    {
        echo "m221215_010047_store_field_layout_in_project_config cannot be reverted.\n";
        return false;
    }
}
