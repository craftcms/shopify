# Release Notes for Shopify

## WIP - 7.1.0

- Added the `totalInventory` product query param.
- Added `craft\shopify\elements\db\ProductQuery::$totalInventory`.
- Added `craft\shopify\elements\db\ProductQuery::totalInventory()`.
- Added `craft\shopify\elements\Product::$totalInventory`.
- Added `craft\shopify\gql\arguments\elements\Product`.
- Added `craft\shopify\gql\interfaces\elements\Product`.
- Added `craft\shopify\gql\types\elements\Product`.
- Added `craft\shopify\gql\types\Image`.
- Added `craft\shopify\gql\types\Metafield`.
- Added `craft\shopify\gql\types\Option`.
- Added `craft\shopify\gql\types\Variant`.

## 7.0.2 - 2026-03-24

- Fixed a PHP error that could occur when authorizing the Shopify app. ([#204](https://github.com/craftcms/shopify/issues/204))

## 7.0.1 - 2026-03-20

- Fixed a PHP error that could occur when retrieving products. ([#202](https://github.com/craftcms/shopify/issues/202))

## 7.0.0 - 2026-03-19

> [!IMPORTANT]
> Shopify for Craft 7.x uses a new app-based authorization system.
> Follow the [upgrade instructions](https://github.com/craftcms/shopify/blob/7.x/README.md#upgrading) to get new credentials.

- Shopify for Craft now requires version `2026-01` of Shopify’s GraphQL Admin API.
- Shopify for Craft now requires Shopify PHP SDK 6.0 or later.
- Added support for setting the base webhook and auth URL using the `SHOPIFY_PUBLIC_DEV_URL` environment variable. ([#185](https://github.com/craftcms/shopify/issues/185))
- Product conditions can now have a “Template Suffix” rule.
- Added the “Shopify Sync” permission.
- Added the `templateSuffix` product query param.
- Added `craft\shopify\collections\VariantCollection`.
- Added `craft\shopify\console\controllers\ApiController`.
- Added `craft\shopify\controllers\AuthController`.
- Added `craft\shopify\db\Table::ACCESS_TOKENS`.
- Added `craft\shopify\elements\conditions\products\TemplateSuffixConditionRule`.
- Added `craft\shopify\elements\db\ProductQuery::$templateSuffix`.
- Added `craft\shopify\elements\db\ProductQuery::templateSuffix()`.
- Added `craft\shopify\events\DefineGqlFieldsEvent`.
- Added `craft\shopify\events\DefineGqlQueryArgumentsEvent`.
- Added `craft\shopify\fieldlayoutelements\MediaField`.
- Added `craft\shopify\fieldlayoutelements\MetafieldsField`.
- Added `craft\shopify\fieldlayoutelements\OptionsField`.
- Added `craft\shopify\fieldlayoutelements\VariantsField`.
- Added `craft\shopify\models\Settings::getAuthUrl()`.
- Added `craft\shopify\models\Settings::getClientId()`.
- Added `craft\shopify\models\Settings::getClientSecret()`.
- Added `craft\shopify\models\Settings::setClientId()`.
- Added `craft\shopify\models\Settings::setClientSecret()`.
- Added `craft\shopify\models\Variant`.
- Added `craft\shopify\records\AccessToken`.
- Added `craft\shopify\services\Api::API_ACCESS_TOKEN_ENV_VAR`.
- Added `craft\shopify\services\Api::EVENT_DEFINE_GQL_QUERY_ARGUMENTS`.
- Added `craft\shopify\services\Api::EVENT_DEFINE_PRODUCT_GQL_FIELDS`.
- Added `craft\shopify\services\Api::getAccessToken()`.
- Added `craft\shopify\services\Api::initializeContext()`.
- `craft\shopify\elements\Product::getCheapeastVariant()` now returns a `craft\shopify\models\Variant` object.
- `craft\shopify\elements\Product::getDefaultVariant()` now returns a `craft\shopify\models\Variant` object.
- `craft\shopify\elements\Product::getVariants()` now returns a collection.
- Deprecated the `--throttle` option for `shopify/sync` commands.
- Deprecated `craft\shopify\models\Settings::getApiKey()`. `getClientId()` should be used instead.
- Deprecated `craft\shopify\models\Settings::getApiSecretKey()`. `getClientSecret()` should be used instead.
- Deprecated `craft\shopify\models\Settings::setApiKey()`. `setClientId()` should be used instead.
- Deprecated `craft\shopify\models\Settings::setApiSecretKey()`. `setClientSecret()` should be used instead.
- Removed the `publishedOnCurrentPublication` product query param.
- Removed `craft\shopify\controllers\ProductsController::actionRenderCardHtml()`.
- Removed `craft\shopify\elements\Product::$publishedOnCurrentPublication`.
- Removed `craft\shopify\elements\Product::getBodyHtml()`.
- Removed `craft\shopify\elements\Product::setBodyHtml()`.
- Removed `craft\shopify\elements\db\ProductQuery::$publishedOnCurrentPublication`.
- Removed `craft\shopify\elements\db\ProductQuery::publishedOnCurrentPublication()`.
- Removed `craft\shopify\handlers\Product`.
- Removed `craft\shopify\helpers\Metafields`.
- Removed `craft\shopify\models\Settings::$syncProductMetafields`.
- Removed `craft\shopify\models\Settings::$syncVariantMetafields`.
- Removed `craft\shopify\services\Products::syncAllProducts()`.
- Fixed a bug where product slugs weren’t syncing correctly.

## 6.1.3 - 2026-01-19

- Fixed a PHP error that could occur when contextual pricing countries aren’t set. ([#191](https://github.com/craftcms/shopify/issues/191))

## 6.1.2 - 2025-12-08

- Fixed a bug where syncing queue jobs could run indefinitely. ([#189](https://github.com/craftcms/shopify/issues/189))
- Fixed a bug where syncing products could fail if the sync file had downloaded incorrectly.

## 6.1.1 - 2025-11-06

- Fixed a bug where file storage could be maxed out when using multiple queue workers.
- Fixed a bug where contextual pricing countries weren’t being force to be capitalized.

## 6.1.0 - 2025-10-24

- Shopify for Craft now supports version `2025-07` of Shopify’s GraphQL Admin API.
- Fixed a PHP error that could occur with missing environment variables. ([#178](https://github.com/craftcms/shopify/issues/178))

## 6.0.5 - 2025-09-09

- Fixed a bug where Shopify data could be overwritten when syncing products. ([#177](https://github.com/craftcms/shopify/issues/177))

## 6.0.4.1 - 2025-08-08

- Fixed a PHP error that could occur when upgrading. ([#176](https://github.com/craftcms/shopify/issues/176))

## 6.0.4 - 2025-08-08

- Fixed a PHP error that occurred when setting the Shopify host name to a non `myshopify` domain. ([#168](https://github.com/craftcms/shopify/issues/168))
- Fixed a PHP error that occurred when creating product drafts. ([#176](https://github.com/craftcms/shopify/issues/176))

## 6.0.3 - 2025-05-27

- Fixed a SQL error that occurred when syncing products on MariaDB. ([#166](https://github.com/craftcms/shopify/issues/166))
- Fixed a SQL error that could occur when upgrading.

## 6.0.2 - 2025-05-19

- Fixed a bug where image sort order wasn’t being respected when syncing products. ([#161](https://github.com/craftcms/shopify/issues/161))

## 6.0.1 - 2025-04-29

- Fixed a PHP error that could occur when viewing Shopify utilities. ([#156](https://github.com/craftcms/shopify/issues/156))
- Fixed a bug where `image` was missing from Shopify variant data.

## 6.0.0 - 2025-04-17

> [!IMPORTANT]
> After updating, go to **Shopify** → **Webhooks** and create the missing webhooks.

- Shopify for Craft now uses the [GraphQL Admin API](https://shopify.dev/docs/api/admin-graphql) to interact with Shopify.
- Shopify now requires Craft CMS 4.15.0+ or 5.0.0+.
- Data syncing is now done via the queue.
- Added the `shopify/data/reset` command.
- Added `craft\shopify\Plugin::getBulkOperation()`.
- Added `craft\shopify\api\BulkDataBatcher`.
- Added `craft\shopify\console\controllers\DataController`.
- Added `craft\shopify\controllers\Sync`.
- Added `craft\shopify\db\ProductQuery::$publishedOnCurrentPublication`.
- Added `craft\shopify\db\ProductQuery::$shopifyGid`.
- Added `craft\shopify\db\ProductQuery::$withAll`.
- Added `craft\shopify\db\ProductQuery::$withImages`.
- Added `craft\shopify\db\ProductQuery::$withMetafields`.
- Added `craft\shopify\db\ProductQuery::$withVariants`.
- Added `craft\shopify\db\ProductQuery::publishedOnCurrentPublication()`.
- Added `craft\shopify\db\ProductQuery::shopifyGid()`.
- Added `craft\shopify\db\ProductQuery::withAll()`.
- Added `craft\shopify\db\ProductQuery::withImages()`.
- Added `craft\shopify\db\ProductQuery::withMetafields()`.
- Added `craft\shopify\db\ProductQuery::withVariants()`.
- Added `craft\shopify\db\Table::DATA`.
- Added `craft\shopify\elements\Product::$publishedOnCurrentPublication`.
- Added `craft\shopify\elements\Product::$shopifyGid`.
- Added `craft\shopify\elements\Product::getData()`.
- Added `craft\shopify\elements\Product::getDescriptionHtml()`.
- Added `craft\shopify\elements\Product::setData()`.
- Added `craft\shopify\elements\Product::setDescriptionHtml()`.
- Added `craft\shopify\helpers\Product::shopifyPublishedHtml()`.
- Added `craft\shopify\helpers\Product::shopifyStatusHtml()`.
- Added `craft\shopify\jobs\ProcessBulkOperationData`.
- Added `craft\shopify\models\BulkOperation`.
- Added `craft\shopify\records\BulkOperation`.
- Added `craft\shopify\records\ShopifyData`.
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
- Added `craft\shopify\services\BulkOperations`.
- Added `craft\shopify\services\Products::deleteShopifyDataByShopifyId()`.
- Added `craft\shopify\services\Products::eagerLoadImagesForProducts()`.
- Added `craft\shopify\services\Products::eagerLoadMetafieldsForProducts()`.
- Added `craft\shopify\services\Products::eagerLoadVariantsForProducts()`.
- Added `craft\shopify\services\Products::normalizeShopifyGid()`.
- `craft\shopify\events\ShopifyProductSyncEvent::$source` now has a type of `array`.
- `craft\shopify\services\Products::createOrUpdateProduct()` no longer has `$metafields` and `$variants` arguments.
- `craft\shopify\services\Products::createOrUpdateProduct()`’s `$product` argument now has a type of `array`.
- Renamed `craft\shopify\handlers\Product` to `Webhook`.
- Deprecated `craft\shopify\elements\Product::getBodyHtml()`. `getDescriptionHtml()` should be used instead.
- Deprecated `craft\shopify\elements\Product::getShopifyStatusHtml()`.  `craft\shopify\helpers\Product::shopifyStatusHtml()` should be used instead.
- Deprecated `craft\shopify\elements\Product::setBodyHtml()`. `setDescriptionHtml()` should be used instead.
- Deprecated `craft\shopify\helpers\Metafields`.
- Deprecated `craft\shopify\models\Settings::$syncProductMetafields`. Metafields are _always_ included when synchronizing product data.
- Deprecated `craft\shopify\models\Settings::$syncVariantMetafields`. Metafields are _always_ included when synchronizing variant data.
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
- Removed `craft\shopify\elements\db\ProductQuery::$publishedScope`.
- Removed `craft\shopify\elements\db\ProductQuery::publishedScope()`.
- Removed `craft\shopify\jobs\UpdateProductMetadata`.
- Removed `craft\shopify\records\ProductData`.
- Removed `craft\shopify\services\Api::SHOPIFY_API_VERSION`.
- Removed `craft\shopify\services\Products::$sleepSeconds`.
- Removed `craft\shopify\services\Products::$throttle`.

## 5.4.1 - 2025-02-12

- Fixed a PHP error that could occur when updating. ([#133](https://github.com/craftcms/shopify/issues/133))

## 5.4.0 - 2025-02-06

- It is now possible to associate Shopify products with elements imported in Feed Me. ([#116](https://github.com/craftcms/shopify/issues/116))
- Added `craft\shopify\feedme\fields\Products`.

## 5.3.1 - 2025-01-28

- Fixed a PHP error that could occur when trying to create webhooks. ([#129](https://github.com/craftcms/shopify/issues/129))

## 5.3.0 - 2024-12-20

- Shopify now requires Craft CMS 4.3.0+ or 5.0.0+.
- Added the “API Version” setting. ([#128](https://github.com/craftcms/shopify/issues/128), [#121](https://github.com/craftcms/shopify/issues/121))
- Added support for selecting products in Link fields. (Craft 5 only.)
- Product syncing now uses presentment prices by default. ([#122](https://github.com/craftcms/shopify/issues/122))
- Added `craft\shopify\linktypes\Product`.
- Added `craft\shopify\models\Settings::getApiVersion()`.
- Added `craft\shopify\models\Settings::setApiVersion()`.
- Added `craft\shopify\services\Api::getMetaFieldClass()`.
- Added `craft\shopify\services\Api::getProductClass()`.
- Added `craft\shopify\services\Api::getSupportedApiVersions()`.
- Added `craft\shopify\services\Api::getVariantClass()`.
- Deprecated `craft\shopify\services\Api::SHOPIFY_API_VERSION`.
- Fixed a bug where variant column data could be larger than a MySQL TEXT column.

## 5.2.0 - 2024-06-18

- `shopify/sync` commands now support a `--throttle` option.
- Fixed a bug where syncing Shopify variants would be limited to 50. ([#115](https://github.com/craftcms/shopify/issues/115))
- Added `craft\shopify\console\controllers\SyncController::$throttle`.
- Added `craft\shopify\services\Products::$throttle`.
- Added `craft\shopify\services\Products::$sleepSeconds`.

## 5.1.2 - 2024-04-24

- Fixed a bug where syncing meta fields would cause Shopify API rate limiting.
- Fixed a bug where variant meta fields weren’t being unpacked.

## 5.1.1 - 2024-04-15

- Fixed a PHP error that could occur when syncing products with emojis. ([#107](https://github.com/craftcms/shopify/issues/107))
- Fixed a PHP error that could occur when syncing products. ([#105](https://github.com/craftcms/shopify/issues/105))

## 5.1.0 - 2024-04-03

- Added support for syncing variant meta fields. ([#99](https://github.com/craftcms/shopify/issues/99))
- Added the `syncProductMetafields` and `syncVariantMetafields` config settings, which can be enabled to sync meta fields.
- Added `craft\shopify\models\Settings::$syncProductMetafields`.
- Added `craft\shopify\models\Settings::$syncVariantMetafields`.

## 5.0.0 - 2024-03-20

- Shopify now requires Craft CMS 5.0.0-beta.10 or later.

## 4.1.2 - 2024-04-15

- Fixed a PHP error that could occur when syncing products with emojis. ([#107](https://github.com/craftcms/shopify/issues/107))
- Fixed a PHP error that could occur when syncing products. ([#105](https://github.com/craftcms/shopify/issues/105))

## 4.1.1 - 2024-04-09

- Fixed a bug where syncing meta fields would cause Shopify API rate limiting.
- Fixed a bug where variant meta fields weren’t being unpacked.

## 4.1.0 - 2024-04-03

- Added support for syncing variant meta fields. ([#99](https://github.com/craftcms/shopify/issues/99))
- Added the `syncProductMetafields` and `syncVariantMetafields` config settings, which can be enabled to sync meta fields.
- Added `craft\shopify\models\Settings::$syncProductMetafields`.
- Added `craft\shopify\models\Settings::$syncVariantMetafields`.

## 4.0.0 - 2023-11-02

> [!IMPORTANT]
> After updating, visit your Shopify store and go to **Settings** → **Apps and sales channels** → **Develop apps** → [your app] → **Configuration**, and update the **Webhook version** setting to `2023-10`.

- Syncing meta fields is no longer performed via a queue job.
- Shopify products’ reference handle is now `shopifyproduct`. ([#77](https://github.com/craftcms/shopify/issues/77))
- Deprecated `craft\shopify\jobs\UpdateProductMetadata`.
- Removed `craft\shopify\events\ShopifyProductSyncEvent::$metafields`. `ShopifyProductSyncEvent::$element->getMetaFields()` can be used instead.
- shopify/shopify-api 5.2.0 or later is now required. ([#81](https://github.com/craftcms/shopify/issues/81), [#84](https://github.com/craftcms/shopify/issues/84))
- Fixed a bug where routes weren’t saving the chosen template.

## 3.2.0 - 2023-06-12

- Added support for syncing variant inventory levels. ([#61](https://github.com/craftcms/shopify/issues/61))
- Added `craft\shopify\elements\db\ProductQuery::publishedScope()`. ([#65](https://github.com/craftcms/shopify/issues/65))
- Fixed a PHP error that occurred when saving the plugin settings in headless mode. ([#68](https://github.com/craftcms/shopify/issues/68))
- Fixed a bug where changes to the product field layout in the project config weren’t applying correctly. ([#52](https://github.com/craftcms/shopify/issues/52))
- Fixed an error that occurred when installing the plugin on PostgreSQL. ([#58](https://github.com/craftcms/shopify/issues/58))

## 3.1.1 - 2023-01-20

- Fixed a SQL error that occurred when syncing products with several tags. ([#54](https://github.com/craftcms/shopify/issues/54))
- Product metadata is now synced via a queue job to avoid the Shopify API rate limiting.

## 3.1.0 - 2022-12-14

- Added the `resave/shopify-products` console command. ([#47](https://github.com/craftcms/shopify/issues/47))
- Products are now automatically re-saved when the “Product URI Format” setting is changed. ([#47](https://github.com/craftcms/shopify/issues/47))
- The product field layout is now stored in the project config.

## 3.0.1 - 2022-11-16

- Fixed a PHP error that occurred when saving invalid settings. ([#39](https://github.com/craftcms/shopify/pull/39), [#40](https://github.com/craftcms/shopify/pull/40))
- Added `craft\shopify\elements\Product::getCheapestVariant()`.
- Added `craft\shopify\elements\Product::getDefaultVariant()`.

## 3.0.0.1 - 2022-11-08

- Fixed a namespacing bug.

## 3.0.0 - 2022-11-08

- Initial release under new management. If you’re upgrading from Shopify Product Fetcher, see [Migrating from v2.x](https://github.com/craftcms/shopify#migrating-from-v2x).
