<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\shopify\handlers;

use craft\shopify\Plugin;
use craft\shopify\enums\WebhookTopics;

/**
 * Webhook handler.
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 6.0.0
 */
class Webhook
{
    public function handle(WebhookTopics $topic, string $shop, array $body): void
    {
        match ($topic) {
            WebhookTopics::ProductsCreate,
            WebhookTopics::ProductsUpdate => Plugin::getInstance()->getProducts()->syncProductByShopifyGid($body['id']),
            WebhookTopics::ProductsDelete => Plugin::getInstance()->getProducts()->deleteProductByShopifyGid($body['id']),
            WebhookTopics::InventoryLevelsUpdate => Plugin::getInstance()->getProducts()->syncProductByInventoryItemId($body['inventory_item_id']),
            WebhookTopics::InventoryItemsUpdate => Plugin::getInstance()->getProducts()->syncProductByInventoryItemId($body['admin_graphql_api_id']),
            WebhookTopics::BulkOperationsFinish => Plugin::getInstance()->getBulkOperations()->handleBulkOperationFinished($body),
            WebhookTopics::ShopUpdate => Plugin::getInstance()->getApi()->getShop(true),
        };
    }
}
