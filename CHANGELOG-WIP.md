# WIP Release Notes for Shopify 8.0

> [!IMPORTANT]
> Ensure the Craft queue is empty before upgrading. Any pending sync jobs will be unable to update their status after the migration runs.
>
> If you change the **Additional Features** or **Custom Scopes** settings after the app is already authorized, you must update the scopes in your Shopify app configuration and then re-authorize the app.

This is primarily a maintenance release, focusing on Shopify API compatibility, authorization, and overall consistency.

Developers should review their templates and extensions for potentially breaking changes to products’ and variants’ `shopifyId` property.
See [Upgrading](https://github.com/craftcms/shopify/blob/8.x/README.md#upgrading) for details.

### Store Management

- Added support for syncing product translations from Shopify. ([#215](https://github.com/craftcms/shopify/issues/215))
- It’s now possible to view the required API scopes in the plugin settings.
- It’s now possible to extend the API scopes with opt-in additional features and custom scopes.
- Added support for Shopify API versions `2026-04` and `2026-07`.
- Product inventory is now synced when Shopify sends `inventory_items/update` webhooks.

### Extensibility

- Added `craft\shopify\auth\OAuthFlow`.
- Added `craft\shopify\clients\GraphqlClient`.
- Added `craft\shopify\controllers\SettingsController::actionGetScopes()`.
- Added `craft\shopify\enums\ApiVersion`.
- Added `craft\shopify\enums\WebhookTopics`.
- Added `craft\shopify\exceptions\InvalidOAuthException`.
- Added `craft\shopify\exceptions\ShopifyApiCommunicationException`.
- Added `craft\shopify\exceptions\ShopifyApiException`.
- Added `craft\shopify\helpers\Metafield`.
- Added `craft\shopify\helpers\ShopifyHelper`.
- Added `craft\shopify\models\Settings::REQUIRED_SCOPES`.
- Added `craft\shopify\models\Settings::getAdditionalFeatures()`.
- Added `craft\shopify\models\Settings::getAdditionalFeaturesOptions()`.
- Added `craft\shopify\models\Settings::getCustomScopes()`.
- Added `craft\shopify\models\Settings::getScopes()`.
- Added `craft\shopify\models\Settings::setAdditionalFeatures()`.
- Added `craft\shopify\models\Settings::setCustomScopes()`.
- Added `craft\shopify\models\Variant::$shopifyGid`.
- Added `craft\shopify\services\Api::connect()`.
- Added `craft\shopify\services\Api::getShopLocalesGql()`.
- Added `craft\shopify\services\Api::getWebhookTopics()`.
- Added `craft\shopify\services\BulkOperations::getBulkOperationByShopifyGid()`.
- Added `craft\shopify\services\Products::deleteProductByShopifyGid()`.
- Added `craft\shopify\services\Products::deleteShopifyDataByShopifyGid()`.
- Added `craft\shopify\services\Products::syncProductByShopifyGid()`.
- Added `craft\shopify\webhooks\WebhookRegistry`.
- `craft\shopify\elements\Product::setMetafields()` and `craft\shopify\models\Variant::setMetafields()` now require a list-shaped array of `{key, value}` objects (or a JSON-encoded string of the same), and throw `\InvalidArgumentException` for anything else. Previously, an associative `key => value` map was also accepted without validation.
- `craft\shopify\handlers\Webhook::handle()` no longer implements `Shopify\Webhooks\Handler`, and its `$topic` argument is now a `craft\shopify\enums\WebhookTopics` enum instead of a string.
- `craft\shopify\models\Variant::$shopifyId` now holds the numeric Shopify ID. The full GID is now available via `$shopifyGid`.
- `craft\shopify\records\ShopifyData::$shopifyId` is now a generated (read-only) column containing the numeric Shopify ID. The full GID is now available via `$shopifyGid`.
- `craft\shopify\services\Api::getGqlClient()` now returns a `craft\shopify\clients\GraphqlClient` instance instead of `Shopify\Clients\Graphql`.
- API and webhook errors are now thrown as `craft\shopify\exceptions\ShopifyApiException` and `craft\shopify\exceptions\InvalidOAuthException`, rather than the `Shopify\Exception\*` classes from the (now-removed) `shopify/shopify-api` package.
- Renamed `craft\shopify\jobs\ProcessBulkOperationData::$bulkOperationShopifyId` to `$bulkOperationShopifyGid`.
- Renamed `craft\shopify\models\BulkOperation::$shopifyId` to `$shopifyGid`.
- Deprecated `craft\shopify\services\BulkOperations::getBulkOperationByShopifyId()`. Use `getBulkOperationByShopifyGid()` instead.
- Deprecated `craft\shopify\services\Products::deleteProductByShopifyId()`. Use `deleteProductByShopifyGid()` instead.
- Deprecated `craft\shopify\services\Products::deleteShopifyDataByShopifyId()`. Use `deleteShopifyDataByShopifyGid()` instead.
- Deprecated `craft\shopify\services\Products::syncProductByShopifyId()`. Use `syncProductByShopifyGid()` instead.
- Removed `craft\shopify\console\controllers\SyncController::$throttle`.
- Removed `craft\shopify\models\Settings::getApiKey()`. Use `getClientId()` instead.
- Removed `craft\shopify\models\Settings::getApiSecretKey()`. Use `getClientSecret()` instead.
- Removed `craft\shopify\models\Settings::setApiKey()`. Use `setClientId()` instead.
- Removed `craft\shopify\models\Settings::setApiSecretKey()`. Use `setClientSecret()` instead.
- Removed `craft\shopify\services\Api::WEBHOOK_TOPICS`. Use `getWebhookTopics()` instead.
- Removed `craft\shopify\services\Api::getSession()`. Use `connect()` instead.
- Removed `craft\shopify\services\Api::initializeContext()`.

### System

- Shopify for Craft now requires Craft CMS 5.10.7 or later. Craft 4 is no longer supported.
- The `shopify_data` table’s `shopifyId` column has been renamed to `shopifyGid`. A new `shopifyId` generated column has been added, set to the numeric ID at the end of the GID.
- The `shopify_bulkoperations` table’s `shopifyId` column has been renamed to `shopifyGid`.
- Removed the `shopify/shopify-api` Composer dependency.
- Fixed a bug where validation errors for the “Context Pricing Countries” setting weren’t displaying correctly.
- Fixed a bug where `inventory_levels/update` webhooks weren’t triggering a product sync.
