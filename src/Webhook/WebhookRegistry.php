<?php

declare(strict_types=1);

namespace CraftCms\Shopify\Webhook;

use CraftCms\Shopify\Api\Api;
use CraftCms\Shopify\Webhook\Enums\WebhookTopics;
use Illuminate\Container\Attributes\Singleton;
use RuntimeException;

/**
 * Registry for Shopify webhook handlers.
 *
 * [[WebhookHandler]] handles every topic the plugin subscribes to. Other code can replace the handler for a topic
 * with [[addHandler()]]. A handler is any object with a `handle(WebhookTopics $topic, string $shop, array $body)` method.
 */
#[Singleton]
class WebhookRegistry
{
    /**
     * @var array<string, object> Handlers, indexed by topic value
     */
    private array $handlers = [];

    public function __construct(Api $api, WebhookHandler $handler)
    {
        foreach ($api->getWebhookTopics() as $topic) {
            $this->addHandler($topic, $handler);
        }
    }

    public function addHandler(WebhookTopics $topic, object $handler): void
    {
        $this->handlers[$topic->value] = $handler;
    }

    public function getHandler(WebhookTopics $topic): ?object
    {
        return $this->handlers[$topic->value] ?? null;
    }

    /**
     * Validates the HMAC of an incoming webhook request and dispatches to the registered handler.
     *
     * @throws RuntimeException on HMAC failure, missing headers, unknown topic, or missing handler.
     */
    public function process(array $headers, string $rawBody, string $secret): void
    {
        $get = static function(array $headers, string $key): string {
            $value = $headers[$key] ?? $headers[strtolower($key)] ?? '';
            return is_array($value) ? ($value[0] ?? '') : (string)$value;
        };

        $topicValue = $get($headers, 'X-Shopify-Topic');
        $shop = $get($headers, 'X-Shopify-Shop-Domain');
        $hmac = $get($headers, 'X-Shopify-Hmac-SHA256');

        if (!$topicValue || !$shop || !$hmac) {
            throw new RuntimeException('Missing required Shopify webhook headers.');
        }

        $expected = base64_encode(hash_hmac('sha256', $rawBody, $secret, true));
        if (!hash_equals($expected, $hmac)) {
            throw new RuntimeException('Webhook HMAC validation failed.');
        }

        $topic = WebhookTopics::tryFrom($topicValue);
        if ($topic === null) {
            throw new RuntimeException("Unknown webhook topic: {$topicValue}");
        }

        $handler = $this->getHandler($topic);
        if (!$handler) {
            throw new RuntimeException("No handler registered for webhook topic: {$topicValue}");
        }

        $body = json_decode($rawBody, true) ?? [];
        $handler->handle($topic, $shop, $body);
    }
}
