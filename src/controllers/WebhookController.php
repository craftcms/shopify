<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\shopify\controllers;

use Craft;
use craft\shopify\Plugin;
use craft\shopify\webhooks\WebhookRegistry;
use craft\web\Controller;
use yii\web\MethodNotAllowedHttpException;
use yii\web\Response as YiiResponse;
use yii\web\ServerErrorHttpException;

/**
 * The WebhookController handles the Shopify webhook request.
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 3.0
 */
class WebhookController extends Controller
{
    public $defaultAction = 'handle';
    public $enableCsrfValidation = false;
    public array|bool|int $allowAnonymous = ['handle'];

    /**
     * Handles the webhooks from Shopify for all topics
     *
     * @return YiiResponse
     * @throws MethodNotAllowedHttpException if no Shopify API session is available
     * @throws ServerErrorHttpException if the webhook could not be processed (e.g. HMAC failure, unknown topic, or a handler error)
     */
    public function actionHandle(): YiiResponse
    {
        $request = Craft::$app->getRequest();

        if (!Plugin::getInstance()->getApi()->connect()) {
            throw new MethodNotAllowedHttpException('No Shopify API session found, check credentials in settings.');
        }

        try {
            WebhookRegistry::process(
                $request->headers->toArray(),
                $request->getRawBody(),
                Plugin::getInstance()->getSettings()->getClientSecret(),
            );
        } catch (\Exception $error) {
            Craft::error($error->getMessage());
            throw new ServerErrorHttpException('Could not process Shopify webhook. Check the logs for more information.', 0, $error);
        }

        $this->response->setStatusCode(200);
        return $this->asRaw('OK');
    }
}
