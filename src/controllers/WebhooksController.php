<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\shopify\controllers;

use Craft;
use craft\shopify\Plugin;
use craft\web\assets\admintable\AdminTableAsset;
use craft\web\Controller;
use GraphQL\InlineFragment;
use GraphQL\Query;
use GraphQL\Variable;
use yii\web\ConflictHttpException;
use yii\web\Response as YiiResponse;

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
     * @return YiiResponse
     */
    public function actionEdit(): YiiResponse
    {
        $view = $this->getView();
        $view->registerAssetBundle(AdminTableAsset::class);
        $api = Plugin::getInstance()->getApi();

        if (!$session = $api->getSession()) {
            throw new ConflictHttpException('No Shopify API session found, check credentials in settings.');
        }

        $webhooks = $api->getWebhooks();

        // If we don't have all webhooks needed for the current environment show the create button
        $containsAllWebhooks = $webhooks->filter(function($item) use ($api) {
            return in_array($item['topic'], $api::WEBHOOK_TOPICS) && $item['endpoint']['callbackUrl'] == Plugin::getInstance()->getSettings()->getWebhookUrl();
        })->count() === count($api::WEBHOOK_TOPICS);

        return $this->renderTemplate('shopify/webhooks/index', compact('webhooks', 'containsAllWebhooks'));
    }

    /**
     * Creates the webhooks for the current environment.
     *
     * @return YiiResponse
     */
    public function actionCreate(): YiiResponse
    {
        $this->requirePostRequest();

        $view = $this->getView();
        $view->registerAssetBundle(AdminTableAsset::class);
        $api = Plugin::getInstance()->getApi();

        if (!$session = $api->getSession()) {
            throw new ConflictHttpException('No Shopify API session found, check credentials in settings.');
        }

        $webhooks = $api->getWebhooks();
        $errors = [];

        // If we don't have all the webhooks loop through the topics and create them if they don't exist
        foreach ($api::WEBHOOK_TOPICS as $topic) {
            // If the webhook already exists skip
            if ($webhooks->filter(function($item) use ($topic) {
                return $item['topic'] === $topic && $item['endpoint']['callbackUrl'] == Plugin::getInstance()->getSettings()->getWebhookUrl();
            })->count() > 0) {
                continue;
            }

            $query = (new \GraphQL\Mutation('webhookSubscriptionCreate'))
                ->setOperationName('webhookSubscriptionCreate')
                ->setVariables([
                    new Variable('topic', 'WebhookSubscriptionTopic!'),
                    new Variable('webhookSubscription', 'WebhookSubscriptionInput!'),
                ])
                ->setArguments([
                    'topic' => '$topic',
                    'webhookSubscription' => '$webhookSubscription',
                ])
                ->setSelectionSet([
                    (new Query('webhookSubscription'))
                        ->setSelectionSet([
                            'id',
                            'topic',
                            'format',
                            (new Query('endpoint'))
                                ->setSelectionSet([
                                    '__typename',
                                    (new InlineFragment('WebhookHttpEndpoint'))
                                        ->setSelectionSet([
                                            'callbackUrl',
                                        ]),
                                ]),
                        ]),
                    (new Query('userErrors'))
                        ->setSelectionSet([
                            'field',
                            'message',
                        ]),
                ]);

            $variables = [
                'topic' => $topic,
                'webhookSubscription' => [
                    'format' => 'JSON',
                    'callbackUrl' => Plugin::getInstance()->getSettings()->getWebhookUrl(),
                ],
            ];

            try {
                $response = $api->getGqlClient()->query(['query' => $query->__toString(), 'variables' => $variables]);
                $body = $response->getDecodedBody();

                if (array_key_exists('errors', $body)) {
                    throw new \Exception($body['errors'][0]['message']);
                }
            } catch (\Exception $e) {
                Craft::error('Could not register webhooks with Shopify API: ' . $e->getMessage(), __METHOD__);
                $errors[] = $e->getMessage();
            }
        }

        if (!empty($errors)) {
            $this->setFailFlash(Craft::t('shopify', 'Webhooks could not be registered.'));
        } else {
            $this->setSuccessFlash(Craft::t('shopify', 'Webhooks registered.'));
        }

        return $this->redirectToPostedUrl();
    }

    /**
     * Deletes a webhook from the Shopify API.
     *
     * @return YiiResponse
     */
    public function actionDelete(): YiiResponse
    {
        $this->requireAcceptsJson();
        $id = Craft::$app->getRequest()->getBodyParam('id');

        $error = null;
        if (Plugin::getInstance()->getApi()->deleteWebhookById($id, $error)) {
            return $this->asSuccess(Craft::t('shopify', 'Webhook deleted'));
        }

        return $this->asFailure($error);
    }
}
