<?php

namespace craft\shopify\migrations;

use Craft;
use craft\db\Migration;
use craft\helpers\ArrayHelper;
use craft\shopify\db\Table;
use craft\shopify\elements\Product;

/**
 * m221118_073752_multi_store migration.
 */
class m221118_073752_multi_store extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp(): bool
    {
        $projectConfig = Craft::$app->getProjectConfig();
        $schemaVersion = $projectConfig->get('plugins.shopify.schemaVersion', true);

        // create store table
        if (!$this->db->tableExists('{{%shopify_stores}}')) {
            $this->createTable(Table::STORES, [
                'id' => $this->primaryKey(),
                'name' => $this->string(),
                'hostName' => $this->string(),
                'apiKey' => $this->string(),
                'apiSecretKey' => $this->string(),
                'accessToken' => $this->string(),
                'uriFormat' => $this->string(),
                'template' => $this->string(),
                'productFieldLayoutId' => $this->integer(),
                'dateCreated' => $this->dateTime()->notNull(),
                'dateUpdated' => $this->dateTime()->notNull(),
                'uid' => $this->string(),
            ]);
        }

        $currentFieldLayout = Craft::$app->getFields()->getLayoutByType(Product::class);

        $this->insert('{{%shopify_stores', [
            'name' => 'Default Store',
            'hostName' => Craft::$app->getProjectConfig()->get('plugins.shopify.settings.hostName', true),
            'accessToken' => Craft::$app->getProjectConfig()->get('plugin.shopify.settings.accessToken', true),
            'apiKey' => Craft::$app->getProjectConfig()->get('plugin.shopify.settings.apiKey', true),
            'apiSecretKey' => Craft::$app->getProjectConfig()->get('plugin.shopify.settings.apiSecretKey', true),
            'template' => Craft::$app->getProjectConfig()->get('plugin.shopify.settings.template', true),
            'uriFormat' => Craft::$app->getProjectConfig()->get('plugin.shopify.settings.uriFormat', true),
            'productFieldLayoutId' => $currentFieldLayout?->id,
        ]);

        $migratableSettings = [];
        // If this is the first time running this migration
        if (version_compare($schemaVersion, '4.1.0', '<')) {
            $migratableSettings = $projectConfig->get('plugins.shopify.settings');
            if ($migratableSettings) {
                $projectConfig->set('plugins.shopify.settings', []);
                $projectConfig->set('plugins.shopify.stores', [
                    'default' => $migratableSettings,
                ]);
            }

        }else{
            $migratableSettings = $projectConfig->get('shopify.settings');
        }

        if (!$this->db->columnExists('{{%shopify_products}}', 'storeId')) {
            $this->addColumn('{{%shopify_products}}', 'storeId', $this->string()->after('id'));
        }

        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown(): bool
    {
        echo "m221118_073752_multi_store cannot be reverted.\n";
        return false;
    }
}
