# Release Notes for Shopify (WIP)

> [!IMPORTANT]
> After updating, go to **Shopify** → **Webhooks** and create missing webhooks.

### Extensibility
- Added `craft\shopify\Plugin::getBulkOperation()`.
- Added `craft\shopify\api\BulkDataBatcher`.
- Added `craft\shopify\controllers\Sync`.
- Added `craft\shopify\db\Table::DATA`.
- Added `craft\shopify\db\ProductQuery::$shopifyGid`.
- Added `craft\shopify\db\ProductQuery::shopifyGid()`.
- Added `craft\shopify\db\ProductQuery::$publishedOnCurrentPublication`.
- Added `craft\shopify\db\ProductQuery::publishedOnCurrentPublication()`.
- Added `craft\shopify\db\ProductQuery::$withAll`.
- Added `craft\shopify\db\ProductQuery::$withMetafields`.
- Added `craft\shopify\db\ProductQuery::$withImages`.
- Added `craft\shopify\db\ProductQuery::$withVariants`.
- Added `craft\shopify\db\ProductQuery::withAll()`.
- Added `craft\shopify\db\ProductQuery::withMetafields()`.
- Added `craft\shopify\db\ProductQuery::withImages()`.
- Added `craft\shopify\db\ProductQuery::withVariants()`.
- Added `craft\shopify\elements\Product::$publishedOnCurrentPublication`.
- Added `craft\shopify\elements\Product::$shopifyGid`.
- Added `craft\shopify\elements\Product::getData()`.
- Added `craft\shopify\elements\Product::setData()`.
- Added `craft\shopify\elements\Product::getDescriptionHtml()`.
- Added `craft\shopify\elements\Product::setDescriptionHtml()`.
- Added `craft\shopify\jobs\ProcessBulkOperationData`.
- Added `craft\shopify\models\BulkOperation`.
- Added `craft\shopify\records\BulkOperation`.
- Added `craft\shopify\records\ShopifyData`.
- Added `craft\shopify\services\BulkOperations`.
- Added `craft\shopify\services\Api::WEBHOOK_TOPICS`.
- Added `craft\shopify\services\Api::createQuery()`.
- Added `craft\shopify\services\Api::deleteWebhookById()`.
- Added `craft\shopify\services\Api::getGqlClient()`.
- Added `craft\shopify\services\Api::getProductGql()`.
- Added `craft\shopify\services\Api::getShop()`.
- Added `craft\shopify\services\Api::getShopGql()`.
- Added `craft\shopify\services\Api::getShopifyDataByType()`.
- Added `craft\shopify\services\Api::getWebhooks()`.
- Added `craft\shopify\services\Api::query()`.
- Added `craft\shopify\services\Products::eagerLoadImagesForProducts()`.
- Added `craft\shopify\services\Products::eagerLoadMetafieldsForProducts()`.
- Added `craft\shopify\services\Products::eagerLoadVariantsForProducts()`.
- `craft\shopify\events\ShopifyProductSyncEvent::$source` now has a type of `array`.
- `craft\shopify\services\Products::createOrUpdateProduct()`’s `$product` argument now has a type of `array`.
- `craft\shopify\services\Products::createOrUpdateProduct()` no longer has `$metafields` and `$variants` arguments.
- Renamed `craft\shopify\handlers\Product` to `Webhook`.
- Deprecated `craft\shopify\elements\Product::getBodyHtml()`. `getDescriptionHtml()` should be used instead.
- Deprecated `craft\shopify\elements\Product::setBodyHtml()`. `setDescriptionHtml()` should be used instead.
- Deprecated `craft\shopify\services\Api::get()`. `query()` should be used instead.
- Deprecated `craft\shopify\services\Api::getAll()`. `query()` should be used instead.
- Deprecated `craft\shopify\services\Api::getAllProducts()`.
- Deprecated `craft\shopify\services\Api::getClient()`. `getGqlClient()` should be used instead.
- Deprecated `craft\shopify\services\Api::getMetafieldsByIdAndOwnerResource()`.
- Deprecated `craft\shopify\services\Api::getMetafieldsByProductId()`.
- Deprecated `craft\shopify\services\Api::getMetafieldsByVariantId()`.
- Deprecated `craft\shopify\services\Api::getProductByShopifyId()`.
- Deprecated `craft\shopify\services\Api::getProductIdByInventoryItemId()`.
- Deprecated `craft\shopify\services\Api::getVariantsByProductId()`.
- Removed `craft\shopify\db\Table::PRODUCTDATA`.
- Removed `craft\shopify\elements\Product::$publishedScope`.
- Removed `craft\shopify\jobs\UpdateProductMetadata`.
- Removed `craft\shopify\records\ProductData`.
- Removed `craft\shopify\services\Api::SHOPIFY_API_VERSION`.
- Removed `craft\shopify\services\Products::$throttle`.
- Removed `craft\shopify\services\Products::$sleepSeconds`.

### System
- Shopify for Craft now uses the GraphQL Admin API to interact with Shopify.
- Data syncing is now done in the queue.