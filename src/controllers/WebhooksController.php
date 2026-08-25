<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\shopify\controllers;

use Craft;
use craft\helpers\Html;
use craft\shopify\exceptions\ShopifyApiException;
use craft\shopify\Plugin;
use craft\web\Controller;
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
     * @inheritdoc
     */
    public function beforeAction($action): bool
    {
        if (!parent::beforeAction($action)) {
            return false;
        }

        // All actions in this controller should be restricted to users with explicit plugin permissions:
        $this->requirePermission('accessPlugin-' . $this->module->id);

        return true;
    }

    /**
     * Edit page for the webhook management
     *
     * @return YiiResponse
     */
    public function actionEdit(): YiiResponse
    {
        $api = Plugin::getInstance()->getApi();

        try {
            $webhooks = $api->getWebhooks();
        } catch (ShopifyApiException $e) {
            throw new ConflictHttpException('There was an issue connecting to the Shopify API. Please check your credentials.');
        }

        $requiredTopics = array_flip(array_map(
            fn($t) => $t->toGraphQLEnum(),
            $api->getWebhookTopics(),
        ));

        foreach ($webhooks as $hook) {
            // When we discover a new topic, yank from the “required” array:
            if (array_key_exists($hook['topic'], $requiredTopics)) {
                unset($requiredTopics[$hook['topic']]);
            }
        }

        // If we saw a hook for every required topic, set a flag:
        // (We use this later to decide whether the "Create webhooks" button should be shown)
        $hasAllHooks = count($requiredTopics) === 0;

        $html = '';

        if ($webhooks->isNotEmpty() && !$hasAllHooks) {
            $html .= Html::beginTag('div', ['class' => 'pane warning']) .
                    Html::tag('p', Craft::t('shopify', 'This environment is not subscribed to all the required webhook topics.')) .
                    Html::beginForm() .
                        Html::actionInput('shopify/webhooks/create') .
                        Html::submitButton(Craft::t('shopify', 'Create missing webhooks'), [
                            'class' => ['btn', 'submit'],
                        ]) .
                    Html::endForm() .
                Html::endTag('div');
        }

        if ($hasAllHooks) {
            $html .= Html::beginTag('div', ['class' => 'pane']) .
                Html::beginTag('p') .
                    Html::tag('span', '', ['class' => 'checkmark-icon']) . ' ' .
                    Craft::t('shopify', 'This environment is subscribed to all the required webhook topics!') .
                Html::endTag('p') .
            Html::endTag('div');
        }

        if ($webhooks->isEmpty()) {
            $html .= Html::beginTag('div', ['class' => 'zilch']) .
                    Html::tag('p', Craft::t('shopify', 'No webhooks exist for this environment.')) .
                Html::endTag('div') .
                Html::beginForm() .
                    Html::actionInput('shopify/webhooks/create') .
                    Html::submitButton(Craft::t('shopify', 'Create all webhooks'), [
                        'class' => ['btn', 'submit'],
                    ]) .
                Html::endForm();
        } else {
            $html .= Html::beginTag('table', ['class' => 'data fullwidth']) .
                Html::beginTag('thead') .
                Html::beginTag('tr') .
                Html::tag('th', Craft::t('shopify', 'Topic')) .
                Html::tag('th', Craft::t('app', 'URI')) .
                Html::tag('th', '') .
                Html::endTag('tr') .
                Html::endTag('thead') .
                Html::beginTag('tbody');

            $webhooks->each(function($hook) use (&$html) {
                $html .= Html::beginTag('tr') .
                    Html::tag('td', $hook['topic']) .
                    Html::tag('td', $hook['uri']) .
                    Html::beginTag('td', ['class' => 'rightalign']) .
                        Html::beginForm(options: [
                            'class' => 'shopify-webhook-delete',
                            'data-confirm' => Craft::t('shopify', 'Are you sure you want to delete the {topic} webhook?', ['topic' => $hook['topic']]),
                        ]) .
                            Html::actionInput('shopify/webhooks/delete') .
                            Html::hiddenInput('id', $hook['id']) .
                            Html::submitButton('', [
                                'class' => 'delete icon',
                                'href' => '#',
                                'title' => Craft::t('shopify', 'Delete {topic} webhook', ['topic' => $hook['topic']]), 'role' => 'button',
                            ]) .
                        Html::endForm() .
                    Html::endTag('td') .
                    Html::endTag('tr');
            });

            $html .= Html::endTag('tbody') .
                Html::endTag('table');

            $js = <<<JS
                (() => {
                    const table = document.querySelector('table.data');
                    const deleteForms = table.querySelectorAll('.shopify-webhook-delete');
                    if (!table || deleteForms.length == 0) return;

                    deleteForms.forEach(deleteForm => {
                        deleteForm.addEventListener('submit', async (e) => {
                            e.preventDefault();
                            
                            if (!confirm(deleteForm.dataset.confirm)) {
                                return;
                            }
                            
                            deleteForm.submit();
                        });
                    });
                })();
            JS;
            $this->getView()->registerJs($js);
        }

        $screen = $this->asCpScreen()
            ->title(Craft::t('shopify', 'Webhooks'))
            ->selectedSubnavItem('webhooks');

        return $screen->contentHtml($html);
    }

    /**
     * Creates the webhooks for the current environment.
     *
     * @return YiiResponse
     */
    public function actionCreate(): ?YiiResponse
    {
        $this->requirePostRequest();
        $api = Plugin::getInstance()->getApi();

        try {
            $webhooks = $api->getWebhooks();
        } catch (ShopifyApiException $e) {
            throw new ConflictHttpException('There was an issue connecting to the Shopify API. Please check your credentials.');
        }

        $errors = [];

        // Check each required topic and create missing subscriptions:
        foreach ($api->getWebhookTopics() as $topic) {
            // Is there at least one webhook with this topic?
            if ($webhooks->contains('topic', $topic->toGraphQLEnum())) {
                continue;
            }

            // Ok, we need to create a subscription with the API:
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
                            'uri',
                        ]),
                    (new Query('userErrors'))
                        ->setSelectionSet([
                            'field',
                            'message',
                        ]),
                ]);

            $variables = [
                'topic' => $topic->toGraphQLEnum(),
                'webhookSubscription' => [
                    'format' => 'JSON',
                    'uri' => Plugin::getInstance()->getSettings()->getWebhookUrl(),
                ],
            ];

            try {
                // Fire it off; if anything goes wrong, we’ll just catch + log it.
                $api->query($query, $variables);
            } catch (ShopifyApiException $e) {
                Craft::error('Could not register webhooks with Shopify API: ' . $e->getMessage(), __METHOD__);
                $errors[] = $e->getMessage();
            }
        }

        if (!empty($errors)) {
            return $this->asFailure(Craft::t('shopify', 'Webhooks could not be registered.'));
        }

        return $this->asSuccess(Craft::t('shopify', 'Webhooks registered.'));
    }

    /**
     * Deletes a webhook from the Shopify API.
     *
     * @return YiiResponse
     */
    public function actionDelete(): YiiResponse
    {
        $this->requirePostRequest();
        $id = Craft::$app->getRequest()->getRequiredBodyParam('id');

        try {
            Plugin::getInstance()->getApi()->deleteWebhookById($id);
        } catch (ShopifyApiException $e) {
            return $this->asFailure(Craft::t('shopify', 'Webhook could not be deleted'));
        }

        return $this->asSuccess(Craft::t('shopify', 'Webhook deleted'));
    }
}
