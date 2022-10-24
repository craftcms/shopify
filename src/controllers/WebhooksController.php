<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\shopify\controllers;

use Craft;
use craft\helpers\App;
use craft\shopify\Plugin;
use craft\web\assets\admintable\AdminTableAsset;
use craft\web\Controller;
use Shopify\Rest\Admin2022_04\Webhook;
use Shopify\Webhooks\Registry;
use Shopify\Webhooks\Topics;
use yii\web\MethodNotAllowedHttpException;
use yii\web\Response;

/**
 * The WebhooksController to manage the Shopify webhooks.
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 3.0
 */
class WebhooksController extends Controller
{
    /**
     * Edit page for the webhook management
     *
     * @return Response
     */
    public function actionEdit(): Response
    {
        $view = $this->getView();
        $view->registerAssetBundle(AdminTableAsset::class);

        if (!$session = Plugin::getInstance()->getApi()->getSession()) {
            throw new MethodNotAllowedHttpException('No Shopify API session found, check credentials in settings.');
        }

        $webhooks = Webhook::all(
            $session, // Session
            [], // Url Ids
            [], // Params
        );

        return $this->renderTemplate('shopify/webhooks/index', compact('webhooks'));
    }

    /**
     * Creates the webhooks for the current environment.
     *
     */
    public function actionCreate(): Response
    {
        $this->requirePostRequest();

        $view = $this->getView();
        $view->registerAssetBundle(AdminTableAsset::class);

        $session = Plugin::getInstance()->getApi()->getSession();
        $pluginSettings = Plugin::getInstance()->getSettings();

        if (!$session) {
            throw new MethodNotAllowedHttpException('No Shopify API session found, check credentials in settings.');
        }

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
            Craft::error('Could not register webhooks with Shopify API.', __METHOD__);
        }

        $this->setSuccessFlash(Craft::t('app', 'Webhooks registered.'));
        return $this->redirectToPostedUrl();
    }

    /**
     * @return Response
     */
    public function actionDelete(): Response
    {
        $this->requireAcceptsJson();
        $id = Craft::$app->getRequest()->getBodyParam('id');

        if ($session = Plugin::getInstance()->getApi()->getSession()) {
            Webhook::delete($session, $id);
            return $this->asSuccess(Craft::t('shopify', 'Webhook deleted'));
        }

        return $this->asSuccess(Craft::t('shopify', 'Webhook could not be deleted'));
    }
}
