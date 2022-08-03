<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\shopify\controllers;

use Craft;
use craft\helpers\App;
use craft\shopify\elements\Product;
use craft\shopify\models\Settings;
use craft\shopify\Plugin;
use craft\web\assets\admintable\AdminTableAsset;
use craft\web\Controller;
use Shopify\Webhooks\Registry;
use Shopify\Webhooks\Topics;
use Shopify\Rest\Admin2022_04\Webhook;
use yii\web\NotFoundHttpException;

/**
 * The SettingsController handles the Shopify webhook request.
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 1.0.0
 */
class SettingsController extends Controller
{

    public $defaultAction = 'index';

    /**
     *
     */
    public function actionIndex(?Settings $settings = null): \yii\web\Response
    {
        $view = $this->getView();
        $view->registerAssetBundle(AdminTableAsset::class);

        if ($settings == null) {
            $settings = Plugin::getInstance()->getSettings();
        }

        $tabs = [
            'apiConnection' => [
                'label' => Craft::t('shopify', 'API Connection'),
                'url' => '#api'
            ],
            'products' => [
                'label' => Craft::t('shopify', 'Products'),
                'url' => '#products'
            ],
        ];


        $webhooks = [];
        if ($session = Plugin::getInstance()->getApi()->getSession()) {
            $webhooks = Webhook::all(
                $session, // Session
                [], // Url Ids
                [], // Params
            );
        }

        return $this->renderTemplate('shopify/settings/index', compact('settings', 'tabs', 'webhooks'));
    }

    /**
     * @return void
     */
    public function actionDeleteWebhook()
    {
        $this->requireAcceptsJson();
        $id = Craft::$app->getRequest()->getBodyParam('id');
        if ($session = Plugin::getInstance()->getApi()->getSession()) {
            Webhook::delete($session, $id);
            return $this->asSuccess('Webhook deleted');
        }
    }

    /**
     *
     */
    public function actionSaveSettings()
    {
        $settings = Craft::$app->getRequest()->getParam('settings');
        $plugin = Craft::$app->getPlugins()->getPlugin('shopify');

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
        $fieldLayoutSuccess = Craft::$app->fields->saveLayout($fieldLayout);
        $plugin->getSettings()->setProductFieldLayout($fieldLayout);

        $webhooksConfigured = false;

        if ($settingsSuccess) {
            $session = Plugin::getInstance()->getApi()->getSession();
            $pluginSettings = Plugin::getInstance()->getSettings();
            if ($session) {
                $responseCreate = Registry::register(
                    path: 'shopify/webhook/handle',
                    topic: Topics::PRODUCTS_CREATE,
                    shop: App::parseEnv($pluginSettings->hostName),
                    accessToken: App::parseEnv($pluginSettings->accessToken)
                );

                $responseUpdate = Registry::register(
                    path: 'shopify/webhook/handle',
                    topic: Topics::PRODUCTS_UPDATE,
                    shop: App::parseEnv($pluginSettings->hostName),
                    accessToken: App::parseEnv($pluginSettings->accessToken)
                );

                $responseDelete = Registry::register(
                    path: 'shopify/webhook/handle',
                    topic: Topics::PRODUCTS_DELETE,
                    shop: App::parseEnv($pluginSettings->hostName),
                    accessToken: App::parseEnv($pluginSettings->accessToken)
                );

                if (!$responseCreate->isSuccess() || !$responseUpdate->isSuccess() || !$responseDelete->isSuccess()) {
                    $webhooksConfigured = false;
                    Craft::error('Could not register webhooks with Shopify API.', __METHOD__);
                } else {
                    $webhooksConfigured = true;
                }
            }
        }

        if ($webhooksConfigured && $settingsSuccess) {
            $this->asSuccess(
                message: Craft::t('shopify', 'Settings saved.'),
            );
        } else {
            $message = Craft::t('shopify', 'Couldn’t save settings.');
            if (!$webhooksConfigured) {
                $message .= ' ' . Craft::t('shopify', 'Could not register webhooks with Shopify API.');
            }
            $this->asFailure(
                message: $message,
                routeParams: ['settings' => $plugin->getSettings()]
            );
        }
    }
}