<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\shopify\webhooks;

/**
 * Registry for Shopify webhook handlers.
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 8.0.0
 */
class WebhookRegistry
{
    private static array $registry = [];

    public static function addHandler(WebhookTopics $topic, object $handler): void
    {
        self::$registry[$topic->value] = $handler;
    }

    public static function getHandler(WebhookTopics $topic): ?object
    {
        return self::$registry[$topic->value] ?? null;
    }

    /**
     * Validates the HMAC of an incoming webhook request and dispatches to the registered handler.
     *
     * @throws \RuntimeException on HMAC failure, missing headers, unknown topic, or missing handler.
     */
    public static function process(array $headers, string $rawBody, string $secret): void
    {
        $get = static function(array $headers, string $key): string {
            $value = $headers[$key] ?? $headers[strtolower($key)] ?? '';
            return is_array($value) ? ($value[0] ?? '') : (string)$value;
        };

        $topicValue = $get($headers, 'X-Shopify-Topic');
        $shop = $get($headers, 'X-Shopify-Shop-Domain');
        $hmac = $get($headers, 'X-Shopify-Hmac-SHA256');

        if (!$topicValue || !$shop || !$hmac) {
            throw new \RuntimeException('Missing required Shopify webhook headers.');
        }

        $expected = base64_encode(hash_hmac('sha256', $rawBody, $secret, true));
        if (!hash_equals($expected, $hmac)) {
            throw new \RuntimeException('Webhook HMAC validation failed.');
        }

        $topic = WebhookTopics::tryFrom($topicValue);
        if ($topic === null) {
            throw new \RuntimeException("Unknown webhook topic: {$topicValue}");
        }

        $handler = self::getHandler($topic);
        if (!$handler) {
            throw new \RuntimeException("No handler registered for webhook topic: {$topicValue}");
        }

        $body = json_decode($rawBody, true) ?? [];
        $handler->handle($topic, $shop, $body);
    }
}
