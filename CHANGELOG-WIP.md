# WIP Release Notes for Shopify 8.0

### Extensibility

- Added `craft\shopify\models\BulkOperation::$shopifyGid`.
- Added `craft\shopify\models\Variant::$shopifyGid`.
- Added `craft\shopify\jobs\ProcessBulkOperationData::$bulkOperationShopifyGid`.
- Added `craft\shopify\services\BulkOperations::getBulkOperationByShopifyGid()`.
- Added `craft\shopify\services\Products::deleteProductByShopifyGid()`.
- Added `craft\shopify\services\Products::deleteShopifyDataByShopifyGid()`.
- Added `craft\shopify\services\Products::syncProductByShopifyGid()`.
- `craft\shopify\models\Variant::$shopifyId` now holds the numeric Shopify ID. The full GID is now available via `$shopifyGid`.
- `craft\shopify\records\ShopifyData::$shopifyId` is now a generated (read-only) column containing the numeric Shopify ID. The full GID is now available via `$shopifyGid`.
- Renamed `craft\shopify\jobs\ProcessBulkOperationData::$bulkOperationShopifyId` to `$bulkOperationShopifyGid`.
- Renamed `craft\shopify\models\BulkOperation::$shopifyId` to `$shopifyGid`.
- Deprecated `craft\shopify\services\BulkOperations::getBulkOperationByShopifyId()`. Use `getBulkOperationByShopifyGid()` instead.
- Deprecated `craft\shopify\services\Products::deleteProductByShopifyId()`. Use `deleteProductByShopifyGid()` instead.
- Deprecated `craft\shopify\services\Products::deleteShopifyDataByShopifyId()`. Use `deleteShopifyDataByShopifyGid()` instead.
- Deprecated `craft\shopify\services\Products::syncProductByShopifyId()`. Use `syncProductByShopifyGid()` instead.

### System

- The `shopify_data` table's `shopifyId` column has been renamed to `shopifyGid`. A new generated `shopifyId` column (the numeric ID at the end of the GID) has been added.
- The `shopify_bulkoperations` table's `shopifyId` column has been renamed to `shopifyGid`.
- Shopify for Craft now requires Craft CMS 5.10.7 or later.
