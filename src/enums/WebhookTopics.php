<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\shopify\enums;

/**
 * Shopify webhook topic constants.
 *
 * Values are in the HTTP header format used by the X-Shopify-Topic header.
 * Use {@see toGraphQLEnum()} when passing a topic to the GraphQL API.
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 8.0.0
 */
enum WebhookTopics: string
{
    case ProductsCreate = 'products/create';
    case ProductsUpdate = 'products/update';
    case ProductsDelete = 'products/delete';
    case InventoryLevelsUpdate = 'inventory_levels/update';
    case InventoryItemsUpdate = 'inventory_items/update';
    case BulkOperationsFinish = 'bulk_operations/finish';
    case ShopUpdate = 'shop/update';

    /**
     * Returns the GraphQL WebhookSubscriptionTopic enum string for this topic.
     *
     * e.g. products/create → PRODUCTS_CREATE
     */
    public function toGraphQLEnum(): string
    {
        return strtoupper(str_replace('/', '_', $this->value));
    }
}
