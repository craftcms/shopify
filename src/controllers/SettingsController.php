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
use yii\web\Response as YiiResponse;

/**
 * The SettingsController handles modifying and saving the general settings.
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 3.0
 */
class SettingsController extends Controller
{
    /**
     * Product index listing
     *
     * @return YiiResponse
     */
    public function actionIndex(?Settings $settings = null): YiiResponse
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
     * @return YiiResponse
     */
    public function actionSaveSettings(): YiiResponse
    {
        $settings = Craft::$app->getRequest()->getParam('settings');
        $plugin = Craft::$app->getPlugins()->getPlugin('shopify');
        /** @var Settings $pluginSettings */
        $pluginSettings = $plugin->getSettings();

        // Remove from editable table namespace
        $settings['uriFormat'] = $settings['routing']['uriFormat'];
        $settings['template'] = $settings['routing']['template'];
        unset($settings['routing']);

        $settingsSuccess = Craft::$app->getPlugins()->savePluginSettings($plugin, $settings);

        $fieldLayout = Craft::$app->getFields()->assembleLayoutFromPost();
        $fieldLayout->type = Product::class;
        Craft::$app->fields->saveLayout($fieldLayout);

        $pluginSettings->setProductFieldLayout($fieldLayout);

        if (!$settingsSuccess) {
            return $this->asFailure(
                message: Craft::t('shopify', 'Couldn’t save settings.'),
                routeParams: ['settings' => $plugin->getSettings()]
            );
        }

        return $this->asSuccess(
            message: Craft::t('shopify', 'Settings saved.'),
        );
    }
}
