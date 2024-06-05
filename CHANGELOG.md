# Release Notes for Shopify

## Unreleased

- It is now possible to throttle the API calls in an attempt to avoid Shopify API rate limiting.
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
