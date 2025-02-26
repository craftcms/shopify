<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\shopify\handlers;

use craft\shopify\Plugin;
use Shopify\Webhooks\Handler;
use Shopify\Webhooks\Topics;

/**
 * Webhook handler.
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 6.0.0
 */
class Webhook implements Handler
{
    public function handle(string $topic, string $shop, array $body): void
    {
        switch ($topic) {
            case Topics::PRODUCTS_UPDATE:
            case Topics::PRODUCTS_CREATE:
                Plugin::getInstance()->getProducts()->syncProductByShopifyId($body['id']);
                break;
            case Topics::PRODUCTS_DELETE:
                Plugin::getInstance()->getProducts()->deleteProductByShopifyId($body['id']);
                break;
            case Topics::INVENTORY_ITEMS_UPDATE:
                Plugin::getInstance()->getProducts()->syncProductByInventoryItemId($body['inventory_item_id']);
                break;
            case Topics::BULK_OPERATIONS_FINISH:
                Plugin::getInstance()->getBulkOperations()->handleBulkOperationFinished($body);
                break;
            case Topics::SHOP_UPDATE:
                // Unfortunately, the shop data in the webhook differs to that returned by the GraphQl API.
                Plugin::getInstance()->getApi()->getShop(true);
                break;
        }
    }
}
