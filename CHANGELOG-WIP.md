# Release Notes for Shopify 5.3 (WIP)

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
