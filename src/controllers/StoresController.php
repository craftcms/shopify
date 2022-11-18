<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\shopify\controllers;

use Craft;
use craft\shopify\elements\Product;
use craft\shopify\models\Settings;
use craft\shopify\Plugin;
use craft\web\Controller;
use craft\web\Response;

/**
 * The StoresController handles modifying and saving the general settings.
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 3.0
 */
class StoresController extends Controller
{

    public function actionIndex(){
        $stores = Plugin::getInstance()->getStores()->getAllStores();
    }

    /**
     * Display a form to allow an administrator to update plugin settings.
     *
     * @return Response
     */
    public function actionIndex(?Settings $settings = null): Response
    {
        if ($settings == null) {
            $settings = Plugin::getInstance()->getSettings();
        }

        $tabs = [
            'apiConnection' => [
                'label' => Craft::t('shopify', 'API Connection'),
                'url' => '#api',
            ],
            'products' => [
                'label' => Craft::t('shopify', 'Products'),
                'url' => '#products',
            ],
        ];

        return $this->renderTemplate('shopify/settings/index', compact('settings', 'tabs'));
    }

    /**
     * Save the settings.
     *
     * @return ?Response
     */
    public function actionSaveSettings(): ?Response
    {
        $settings = Craft::$app->getRequest()->getParam('settings');
        $plugin = Plugin::getInstance();
        /** @var Settings $pluginSettings */
        $pluginSettings = $plugin->getSettings();

        // Remove from editable table namespace
        $settings['uriFormat'] = $settings['routing']['uriFormat'];
        $settings['template'] = $settings['routing']['template'];
        unset($settings['routing']);

        $settingsSuccess = Craft::$app->getPlugins()->savePluginSettings($plugin, $settings);

        $fieldLayout = Craft::$app->getFields()->assembleLayoutFromPost();
        $fieldLayout->type = Product::class;
        if(Craft::$app->fields->saveLayout($fieldLayout)){

        };

        $pluginSettings->setProductFieldLayout($fieldLayout);

        if (!$settingsSuccess) {
            return $this->asModelFailure(
                $pluginSettings,
                Craft::t('shopify', 'Couldn’t save settings.'),
                'settings',
            );
        }

        return $this->asModelSuccess(
            $pluginSettings,
            Craft::t('shopify', 'Settings saved.'),
            'settings',
        );
    }
}
