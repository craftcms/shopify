# WIP Release Notes for Shopify 8.0

> [!IMPORTANT]
> If you change the **Additional Features** or **Custom Scopes** settings after the app is already authorized, you must update the scopes in your Shopify app configuration and then re-authorize the app.

### Store Management

- Added support for syncing product translations from Shopify. ([#215](https://github.com/craftcms/shopify/issues/215))
- It’s now possible to view the required API scopes in the plugin settings.
- It’s now possible to extend the API scopes with opt-in additional features and custom scopes.
- It’s now possible to customize the Shopify API context before and after initialization via new events.

### Extensibility

- Added `craft\shopify\controllers\SettingsController::actionGetScopes()`.
- Added `craft\shopify\events\DefineInitializeApiContextEvent`.
- Added `craft\shopify\models\BulkOperation::$shopifyGid`.
- Added `craft\shopify\models\Settings::REQUIRED_SCOPES`.
- Added `craft\shopify\models\Settings::getAdditionalFeatures()`.
- Added `craft\shopify\models\Settings::getAdditionalFeaturesOptions()`.
- Added `craft\shopify\models\Settings::getCustomScopes()`.
- Added `craft\shopify\models\Settings::getScopes()`.
- Added `craft\shopify\models\Settings::setAdditionalFeatures()`.
- Added `craft\shopify\models\Settings::setCustomScopes()`.
- Added `craft\shopify\models\Variant::$shopifyGid`.
- Added `craft\shopify\jobs\ProcessBulkOperationData::$bulkOperationShopifyGid`.
- Added `craft\shopify\services\BulkOperations::getBulkOperationByShopifyGid()`.
- Added `craft\shopify\services\Products::deleteProductByShopifyGid()`.
- Added `craft\shopify\services\Products::deleteShopifyDataByShopifyGid()`.
- Added `craft\shopify\services\Products::syncProductByShopifyGid()`.
- Added `craft\shopify\services\Api::EVENT_AFTER_INITIALIZE_API_CONTEXT`.
- Added `craft\shopify\services\Api::EVENT_DEFINE_INITIALIZE_API_CONTEXT`.
- Added `craft\shopify\services\Api::getShopLocalesGql()`.
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
- Fixed a bug where validation errors for the "Context Pricing Countries" setting weren't displaying correctly.
- Shopify for Craft now requires Craft CMS 5.10.7 or later.
