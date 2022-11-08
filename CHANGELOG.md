# Release Notes for Shopify

## 3.0.0

### Changed
- Webhooks now keep product information syncronized between Shopify and Craft.
- The Shopify product element relation field replaces the previous product field.
- Shopify API version is now `2020-10`.
- Added `craft\shopify\console\controllers\SyncController`
- Added `craft\shopify\controllers\ProductsController`
- Added `craft\shopify\controllers\SettingsController`
- Added `craft\shopify\controllers\WebhookController`
- Added `craft\shopify\controllers\WebhooksController`
- Added `craft\shopify\db\Table`
- Added `craft\shopify\elements\Product`
- Added `craft\shopify\elements\conditions\products\HandleConditionRule`
- Added `craft\shopify\elements\conditions\products\ProductConditionRule`
- Added `craft\shopify\elements\conditions\products\ProductTypeConditionRule`
- Added `craft\shopify\elements\conditions\products\ShopidyStatusConditionRule`
- Added `craft\shopify\elements\conditions\products\TagsConditionRule`
- Added `craft\shopify\elements\conditions\products\VendorConditionRule`
- Added `craft\shopify\elements\db\ProductQuery`
- Added `craft\shopify\events\ShopifyProductSyncEvent`
- Added `craft\shopify\fields\Products`
- Added `craft\shopify\helpers\Metafields`
- Added `craft\shopify\helpers\Product`
- Added `craft\shopify\models\Settings`
- Added `craft\shopify\records\ProductData`
- Added `craft\shopify\records\Product`
- Added `craft\shopify\services\Api`
- Added `craft\shopify\services\Products`
- Added `craft\shopify\services\Store`
- Added `craft\shopify\utilities\Sync`