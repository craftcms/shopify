# WIP Release Notes for Shopify 8.0

> [!IMPORTANT]
> If you change the **Additional Features** or **Custom Scopes** settings after the app is already authorized, you must update the scopes in your Shopify app configuration and then re-authorize the app.

### Store Management

- Added support for syncing product translations from Shopify. ([#215](https://github.com/craftcms/shopify/issues/215))
- It’s now possible to view the required API scopes in the plugin settings.
- It’s now possible to extend the API scopes with opt-in additional features and custom scopes.
- Added support for the 2026-04 and 2026-07 Shopify API versions.
- Product inventory now also syncs when Shopify sends an `inventory_items/update` webhook.

### Extensibility

- Added `craft\shopify\controllers\SettingsController::actionGetScopes()`.
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
- Added `craft\shopify\services\Api::getShopLocalesGql()`.
- Added `craft\shopify\services\Api::connect()`.
- Added `craft\shopify\services\Api::getWebhookTopics()`.
- Added `craft\shopify\auth\OAuthFlow`.
- Added `craft\shopify\clients\GraphqlClient`.
- Added `craft\shopify\enums\ApiVersion`.
- Added `craft\shopify\enums\WebhookTopics`.
- Added `craft\shopify\exceptions\InvalidOAuthException`.
- Added `craft\shopify\exceptions\ShopifyApiCommunicationException`.
- Added `craft\shopify\exceptions\ShopifyApiException`.
- Added `craft\shopify\helpers\ShopifyHelper`.
- Added `craft\shopify\webhooks\WebhookRegistry`.
- `craft\shopify\models\Variant::$shopifyId` now holds the numeric Shopify ID. The full GID is now available via `$shopifyGid`.
- `craft\shopify\records\ShopifyData::$shopifyId` is now a generated (read-only) column containing the numeric Shopify ID. The full GID is now available via `$shopifyGid`.
- `craft\shopify\services\Api::getGqlClient()` now returns a `craft\shopify\clients\GraphqlClient` instance instead of `Shopify\Clients\Graphql`.
- `craft\shopify\handlers\Webhook::handle()` no longer implements `Shopify\Webhooks\Handler`, and its `$topic` argument is now a `craft\shopify\enums\WebhookTopics` enum instead of a string.
- API and webhook errors are now thrown as `craft\shopify\exceptions\ShopifyApiException` and `craft\shopify\exceptions\InvalidOAuthException`, rather than the `Shopify\Exception\*` classes from the (now-removed) `shopify/shopify-api` package.
- Renamed `craft\shopify\jobs\ProcessBulkOperationData::$bulkOperationShopifyId` to `$bulkOperationShopifyGid`.
- Renamed `craft\shopify\models\BulkOperation::$shopifyId` to `$shopifyGid`.
- Deprecated `craft\shopify\services\BulkOperations::getBulkOperationByShopifyId()`. Use `getBulkOperationByShopifyGid()` instead.
- Deprecated `craft\shopify\services\Products::deleteProductByShopifyId()`. Use `deleteProductByShopifyGid()` instead.
- Deprecated `craft\shopify\services\Products::deleteShopifyDataByShopifyId()`. Use `deleteShopifyDataByShopifyGid()` instead.
- Deprecated `craft\shopify\services\Products::syncProductByShopifyId()`. Use `syncProductByShopifyGid()` instead.
- Removed `craft\shopify\services\Api::getSession()`. Use `connect()` instead.
- Removed `craft\shopify\services\Api::initializeContext()`.
- Removed `craft\shopify\services\Api::WEBHOOK_TOPICS`. Use `getWebhookTopics()` instead.

### System

- The `shopify_data` table's `shopifyId` column has been renamed to `shopifyGid`. A new generated `shopifyId` column (the numeric ID at the end of the GID) has been added.
- The `shopify_bulkoperations` table's `shopifyId` column has been renamed to `shopifyGid`.
- Fixed a bug where validation errors for the "Context Pricing Countries" setting weren't displaying correctly.
- Fixed a bug where `inventory_levels/update` webhooks weren't triggering a product sync.
- Removed the `shopify/shopify-api` Composer dependency.
- Shopify for Craft now requires Craft CMS 5.10.7 or later.
