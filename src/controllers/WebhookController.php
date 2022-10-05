<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\shopify\controllers;

use Craft;
use craft\shopify\Plugin;
use craft\web\Controller;
use Shopify\Webhooks\Registry;
use yii\web\MethodNotAllowedHttpException;
use yii\web\Response;

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
     */
    public function actionHandle(): Response
    {
        $request = Craft::$app->getRequest();

        if (!Plugin::getInstance()->getApi()->getSession()) {
            throw new MethodNotAllowedHttpException('No Shopify API session found, check credentials in settings.');
        }

        try {
            $response = Registry::process($request->headers->toArray(), $request->getRawBody());

            if (!$response->isSuccess()) {
                Craft::error("Webhook handler failed with message:" . $response->getErrorMessage());
            }
        } catch (\Exception $error) {
            Craft::error($error->getMessage());
        }

        $this->response->setStatusCode(200);
        return $this->asRaw('OK');
    }
}
