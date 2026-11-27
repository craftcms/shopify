<?php

declare(strict_types=1);

namespace CraftCms\Shopify\Webhook;

use CraftCms\Shopify\Api\Api;
use CraftCms\Shopify\BulkOperation\BulkOperations;
use CraftCms\Shopify\Product\Products;
use CraftCms\Shopify\Webhook\Enums\WebhookTopics;

/**
 * Handles the webhook topics the plugin subscribes to.
 */
readonly class WebhookHandler
{
    public function __construct(
        private Api $api,
        private BulkOperations $bulkOperations,
        private Products $products,
    ) {
    }

    public function handle(WebhookTopics $topic, string $shop, array $body): void
    {
        // Shopify sends numeric IDs as JSON numbers.
        match ($topic) {
            WebhookTopics::ProductsCreate,
            WebhookTopics::ProductsUpdate => $this->products->syncProductByShopifyGid((string)$body['id']),
            WebhookTopics::ProductsDelete => $this->products->deleteProductByShopifyGid((string)$body['id']),
            WebhookTopics::InventoryLevelsUpdate => $this->products->syncProductByInventoryItemId($body['inventory_item_id']),
            WebhookTopics::InventoryItemsUpdate => $this->products->syncProductByInventoryItemId($body['admin_graphql_api_id']),
            WebhookTopics::BulkOperationsFinish => $this->bulkOperations->handleBulkOperationFinished($body),
            WebhookTopics::ShopUpdate => $this->api->getShop(true),
        };
    }
}
