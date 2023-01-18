# Release Notes for Shopify

## Unreleased

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
