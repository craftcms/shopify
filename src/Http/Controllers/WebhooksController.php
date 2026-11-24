<?php

declare(strict_types=1);

namespace CraftCms\Shopify\Http\Controllers;

use craft\shopify\exceptions\ShopifyApiException;
use craft\shopify\services\Api;
use CraftCms\Cms\Http\RespondsWithFlash;
use CraftCms\Cms\Http\Responses\CpScreenResponse;
use CraftCms\Cms\Ui\Nodes\Callout;
use CraftCms\Cms\Ui\Nodes\Table;
use CraftCms\Cms\Ui\Ui;
use CraftCms\Shopify\Settings;
use GraphQL\Mutation;
use GraphQL\Query;
use GraphQL\Variable;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

use function CraftCms\Cms\action_url;
use function CraftCms\Cms\t;

/**
 * Manages the store’s webhook subscriptions for this environment.
 */
readonly class WebhooksController
{
    use RespondsWithFlash;

    public function __construct(
        private Api $api,
    ) {
    }

    public function index(): CpScreenResponse
    {
        $webhooks = $this->getWebhooks();
        $missingTopics = $this->missingTopics($webhooks);

        $ui = Ui::make();

        if ($webhooks->isEmpty()) {
            $ui->add(Callout::make('no-webhooks', t('No webhooks exist for this environment.', category: 'shopify')));
        } elseif ($missingTopics) {
            $ui->add(Callout::make('missing-webhooks', t('This environment is not subscribed to all the required webhook topics.', category: 'shopify'))
                ->variant('warning'));
        } else {
            $ui->add(Callout::make('all-webhooks', t('This environment is subscribed to all the required webhook topics!', category: 'shopify'))
                ->variant('success'));
        }

        if ($webhooks->isNotEmpty()) {
            $ui->add(Table::make('webhooks')
                ->columns([
                    ['key' => 'topic', 'label' => t('Topic', category: 'shopify'), 'thin' => true],
                    ['key' => 'uri', 'label' => t('URI'), 'wrap' => true],
                ])
                ->rows($webhooks->map(fn(array $hook) => [
                    'id' => $hook['id'],
                    'topic' => $hook['topic'],
                    'uri' => $hook['uri'],
                ])->values()->all())
                ->deletable(
                    action_url('shopify/webhooks/delete'),
                    t('Are you sure you want to delete this webhook?', category: 'shopify'),
                ));
        }

        $response = new CpScreenResponse()
            ->title(t('Webhooks', category: 'shopify'))
            ->selectedSubnavItem('webhooks');

        if (!$missingTopics) {
            return $response->ui($ui);
        }

        return $response
            ->submitButtonLabel($webhooks->isEmpty()
                ? t('Create all webhooks', category: 'shopify')
                : t('Create missing webhooks', category: 'shopify'))
            ->redirectUrl('shopify/webhooks')
            ->ui($ui, props: [
                'submit' => [
                    'method' => 'post',
                    'url' => action_url('shopify/webhooks/create'),
                ],
            ]);
    }

    public function store(Settings $settings): Response
    {
        $webhooks = $this->getWebhooks();
        $errors = [];

        foreach ($this->missingTopics($webhooks) as $topic) {
            $query = (new Mutation('webhookSubscriptionCreate'))
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
                    (new Query('webhookSubscription'))->setSelectionSet(['id', 'topic', 'format', 'uri']),
                    (new Query('userErrors'))->setSelectionSet(['field', 'message']),
                ]);

            try {
                $this->api->query($query, [
                    'topic' => $topic,
                    'webhookSubscription' => [
                        'format' => 'JSON',
                        'uri' => $settings->getWebhookUrl(),
                    ],
                ]);
            } catch (ShopifyApiException $e) {
                Log::error('Could not register webhooks with Shopify API: ' . $e->getMessage());
                $errors[] = $e->getMessage();
            }
        }

        if ($errors) {
            return $this->asFailure(t('Webhooks could not be registered.', category: 'shopify'));
        }

        return $this->asSuccess(t('Webhooks registered.', category: 'shopify'));
    }

    public function destroy(Request $request): Response
    {
        $id = $request->input('id');
        abort_unless(is_string($id) && $id !== '', 400, 'Missing webhook id');

        try {
            $this->api->deleteWebhookById($id);
        } catch (ShopifyApiException) {
            return $this->asFailure(t('Webhook could not be deleted', category: 'shopify'));
        }

        return $this->asSuccess(t('Webhook deleted', category: 'shopify'));
    }

    /**
     * @return Collection<int, array{id: string, topic: string, uri: string}>
     */
    private function getWebhooks(): Collection
    {
        try {
            return $this->api->getWebhooks();
        } catch (ShopifyApiException) {
            abort(409, 'There was an issue connecting to the Shopify API. Please check your credentials.');
        }
    }

    /**
     * Returns the GraphQL enum values of the webhook topics that have no subscription yet.
     *
     * @param Collection<int, array{id: string, topic: string, uri: string}> $webhooks
     * @return string[]
     */
    private function missingTopics(Collection $webhooks): array
    {
        $topics = array_map(fn($topic) => $topic->toGraphQLEnum(), $this->api->getWebhookTopics());

        return array_values(array_diff($topics, $webhooks->pluck('topic')->all()));
    }
}
