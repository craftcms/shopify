<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\shopify\controllers;

use Craft;
use craft\helpers\StringHelper;
use craft\queue\jobs\ResaveElements;
use craft\shopify\elements\Product;
use craft\shopify\models\Settings;
use craft\shopify\Plugin;
use craft\web\Controller;
use yii\web\Response;

/**
 * The SettingsController handles modifying and saving the general settings.
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 3.0
 */
class SettingsController extends Controller
{
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
        $originalUriFormat = $pluginSettings->uriFormat;

        // Remove from editable table namespace
        $settings['uriFormat'] = $settings['routing']['uriFormat'];
        if (!Craft::$app->getConfig()->getGeneral()->headlessMode) {
            $settings['template'] = $settings['routing']['template'];
        }
        unset($settings['routing']);

        $settingsSuccess = Craft::$app->getPlugins()->savePluginSettings($plugin, $settings);

        $fieldLayout = Craft::$app->getFields()->assembleLayoutFromPost();
        $fieldLayout->type = Product::class;

        $projectConfig = Craft::$app->getProjectConfig();
        $uid = StringHelper::UUID();
        $fieldLayoutConfig = $fieldLayout->getConfig();
        $projectConfig->set(Plugin::PC_PATH_PRODUCT_FIELD_LAYOUTS, [$uid => $fieldLayoutConfig], 'Save the Shopify product field layout');

        $pluginSettings->setProductFieldLayout($fieldLayout);

        if (!$settingsSuccess) {
            return $this->asModelFailure(
                $pluginSettings,
                Craft::t('shopify', 'Couldn’t save settings.'),
                'settings',
            );
        }

        // Resave all products if the URI format changed
        if ($originalUriFormat != $settings['uriFormat']) {
            Craft::$app->getQueue()->push(new ResaveElements([
                'elementType' => Product::class,
                'criteria' => [
                    'siteId' => '*',
                    'unique' => true,
                    'status' => null,
                ],
            ]));
        }

        return $this->asModelSuccess(
            $pluginSettings,
            Craft::t('shopify', 'Settings saved.'),
            'settings',
        );
    }
}
