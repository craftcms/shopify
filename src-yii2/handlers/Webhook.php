<?php

namespace craft\shopify\handlers;

/** @deprecated in 9.0.0. Use {@see \CraftCms\Shopify\Webhook\WebhookHandler} instead. */
class_alias(\CraftCms\Shopify\Webhook\WebhookHandler::class, 'craft\shopify\handlers\Webhook');

/** @phpstan-ignore-next-line Gives IDEs and static analysis a real symbol. */
if (false) {
    readonly class Webhook extends \CraftCms\Shopify\Webhook\WebhookHandler
    {
    }
}
