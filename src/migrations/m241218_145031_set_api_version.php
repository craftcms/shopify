<?php

namespace craft\shopify\migrations;

use Craft;
use craft\db\Migration;

/**
 * m241218_145031_set_api_version migration.
 */
class m241218_145031_set_api_version extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp(): bool
    {
        // Don't make the same config changes twice
        $projectConfig = Craft::$app->getProjectConfig();
        $schemaVersion = $projectConfig->get('plugins.shopify.schemaVersion', true);

        if (version_compare($schemaVersion, '5.3.0', '<')) {
            $muteEvents = $projectConfig->muteEvents;
            $projectConfig->muteEvents = true;

            $projectConfig->set('plugins.shopify.settings.apiVersion', '2023-10', 'Save the current Shopify API version');
            $projectConfig->muteEvents = $muteEvents;
        }

        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown(): bool
    {
        echo "m241218_145031_set_api_version cannot be reverted.\n";
        return false;
    }
}
