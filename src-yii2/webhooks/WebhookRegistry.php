<?php

namespace craft\shopify\webhooks;

use CraftCms\Shopify\Webhook\Enums\WebhookTopics;
use CraftCms\Shopify\Webhook\WebhookRegistry as NativeWebhookRegistry;

/**
 * Forwards the static API of the old registry to the native one, which is a singleton.
 *
 * @deprecated in 9.0.0. Use {@see NativeWebhookRegistry} instead.
 */
class WebhookRegistry
{
    public static function addHandler(WebhookTopics $topic, object $handler): void
    {
        app(NativeWebhookRegistry::class)->addHandler($topic, $handler);
    }

    public static function getHandler(WebhookTopics $topic): ?object
    {
        return app(NativeWebhookRegistry::class)->getHandler($topic);
    }

    public static function process(array $headers, string $rawBody, string $secret): void
    {
        app(NativeWebhookRegistry::class)->process($headers, $rawBody, $secret);
    }
}
