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
use yii\web\NotFoundHttpException;

/**
 * The SettingsController handles the Shopify webhook request.
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 3.0
 */
class SettingsController extends Controller
{
    public $defaultAction = 'index';

    /**
     *
     */
    public function actionIndex(?Settings $settings = null): \yii\web\Response
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
     *
     */
    public function actionSaveSettings()
    {
        $settings = Craft::$app->getRequest()->getParam('settings');
        $plugin = Craft::$app->getPlugins()->getPlugin('shopify');
        /** @var Settings $pluginSettings */
        $pluginSettings = $plugin->getSettings();

        if ($plugin === null) {
            throw new NotFoundHttpException('Shopify plugin not found');
        }

        // Remove from editable table namespace
        $settings['uriFormat'] = $settings['routing']['uriFormat'];
        $settings['template'] = $settings['routing']['template'];
        unset($settings['routing']);

        $settingsSuccess = Craft::$app->getPlugins()->savePluginSettings($plugin, $settings);

        $fieldLayout = Craft::$app->getFields()->assembleLayoutFromPost();
        $fieldLayout->type = Product::class;
        Craft::$app->fields->saveLayout($fieldLayout);

        $pluginSettings->setProductFieldLayout($fieldLayout);

        if ($settingsSuccess) {
            $this->asSuccess(
                message: Craft::t('shopify', 'Settings saved.'),
            );
        } else {
            $message = Craft::t('shopify', 'Couldn’t save settings.');
            $this->asFailure(
                message: $message,
                routeParams: ['settings' => $plugin->getSettings()]
            );
        }
    }
}
