# Release Notes for Shopify 5.3 (WIP)

### Content Management
- Added support for selecting products in Link fields.
- Syncing products now returns presentment prices by default. ([#122](https://github.com/craftcms/shopify/issues/122))

### Administration
- It is now possible to set the API version in the plugin settings. ([#128](https://github.com/craftcms/shopify/issues/128), [#121](https://github.com/craftcms/shopify/issues/121))
- Added the `apiVersion` config setting, which determines which version of the Shopify API to use. ([#128](https://github.com/craftcms/shopify/issues/128), [#121](https://github.com/craftcms/shopify/issues/121))

### Extensibility
- Added `craft\shopify\linktypes\Product`.
- Added `craft\shopify\models\Settings::getApiVersion()`.
- Added `craft\shopify\models\Settings::setApiVersion()`.
- Added `craft\shopify\services\Api::getMetaFieldClass()`.
- Added `craft\shopify\services\Api::getProductClass()`.
- Added `craft\shopify\services\Api::getSupportedApiVersions()`.
- Added `craft\shopify\services\Api::getVariantClass()`.
- Deprecated `craft\shopify\services\Api::SHOPIFY_API_VERSION`.
- 
### System
- Added Craft CMS 4 compatibility.