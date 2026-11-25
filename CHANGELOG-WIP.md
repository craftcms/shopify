# Release Notes for Shopify 9.0 (WIP)

> [!IMPORTANT]
> Shopify 9.0 requires Craft CMS 6 and PHP 8.5. See [UPGRADE.md](UPGRADE.md) for upgrade steps and breaking changes.

### System

- Shopify now requires Craft CMS 6.0 or later.
- Shopify now requires PHP 8.5 or later.
- Shopify now requires `craftcms/yii2-adapter`.
- Console commands are now Artisan commands, such as `php artisan shopify:sync:products`. The old command names still work as aliases.
- Shopify 9.0 can only be installed over Shopify 8.2.1 or later.
- Shopify must be re-authorized after the upgrade if Shopify 8 stored its access token in the database rather than in the `.env` file, because Craft 6 can’t decrypt it.
- Shopify’s config file is now `config/craft/shopify.php`.
- The product edit screen now shows the Shopify details in the sidebar’s metadata.
- Syncing products from Shopify no longer creates a revision of each product.
- Fixed a bug where garbage collection never deleted products that had been removed from Shopify.
- Fixed a SQL error when the product index was sorted by Variants or Shopify Edit. They’re no longer sort options.

### Extensibility

- Renamed `craft\shopify\Plugin` to `CraftCms\Shopify\Plugin`. The old class name is a deprecated alias.
- Renamed `craft\shopify\models\Settings` to `CraftCms\Shopify\Settings`, which now extends `CraftCms\Cms\Plugin\PluginSettings`. The old class name is a deprecated alias.
- Added `CraftCms\Shopify\Database\Table`.
- Added `CraftCms\Shopify\GarbageCollection\Actions\DeleteRemovedProducts`.
- Added `CraftCms\Shopify\GarbageCollection\Actions\PurgeBulkOperations`.
- `craft.shopify` and `craft.shopifyProducts()` are now `CraftCms\Cms\Twig\Variables\CraftVariable` macros.
- Removed `craft\shopify\web\twig\CraftVariableBehavior`.
- Added `CraftCms\Shopify\Api\Events\GqlQueryArgumentsResolving`, which replaces `craft\shopify\services\Api::EVENT_DEFINE_GQL_QUERY_ARGUMENTS`.
- Added `CraftCms\Shopify\Api\Events\ProductGqlFieldsResolving`, which replaces `craft\shopify\services\Api::EVENT_DEFINE_PRODUCT_GQL_FIELDS`.
- Added `CraftCms\Shopify\Product\Events\ProductSyncing`, which replaces `craft\shopify\services\Products::EVENT_BEFORE_SYNCHRONIZE_PRODUCT`.
- Renamed `craft\shopify\services\Api` to `CraftCms\Shopify\Api\Api`. The old class name is a deprecated alias.
- Renamed `craft\shopify\services\BulkOperations` to `CraftCms\Shopify\BulkOperation\BulkOperations`. The old class name is a deprecated alias.
- Renamed `craft\shopify\services\Products` to `CraftCms\Shopify\Product\Products`. The old class name is a deprecated alias.
- Renamed `craft\shopify\services\Store` to `CraftCms\Shopify\Store\Store`. The old class name is a deprecated alias.
- The Shopify services no longer extend `craft\base\Component`.
- Renamed `craft\shopify\records\AccessToken` to `CraftCms\Shopify\Api\Models\AccessToken`, which is now an Eloquent model. The old class name is a deprecated alias.
- Renamed `craft\shopify\records\BulkOperation` to `CraftCms\Shopify\BulkOperation\Models\BulkOperation`, which is now an Eloquent model. The old class name is a deprecated alias.
- Renamed `craft\shopify\records\Product` to `CraftCms\Shopify\Product\Models\Product`, which is now an Eloquent model. The old class name is a deprecated alias.
- Renamed `craft\shopify\records\ShopifyData` to `CraftCms\Shopify\Api\Models\ShopifyData`, which is now an Eloquent model. The old class name is a deprecated alias.
- Renamed `craft\shopify\helpers\Metafield` to `CraftCms\Shopify\Helpers\Metafield`. The old class name is a deprecated alias.
- Renamed `craft\shopify\helpers\ShopifyHelper` to `CraftCms\Shopify\Helpers\ShopifyHelper`. The old class name is a deprecated alias.
- `CraftCms\Shopify\Api\Api::getShopifyDataByType()` now returns `CraftCms\Shopify\Api\Models\ShopifyData` models when `$returnRecords` is `true`.
- `CraftCms\Shopify\Api\Api::getGqlClient()` and `CraftCms\Shopify\Store\Store::getUrl()` now throw a `RuntimeException` instead of `yii\base\InvalidConfigException`.
- `CraftCms\Shopify\BulkOperation\BulkOperations::MUTEX_NAME` now names a Laravel cache lock.
- `CraftCms\Shopify\Product\Products::handleChangedFieldLayout()` now accepts a `CraftCms\Cms\ProjectConfig\Events\ConfigEvent` object.
- Removed `craft\shopify\models\Settings::setAdditionalFeatures()`, `setApiVersion()`, `setClientId()`, `setClientSecret()`, `setContextualPricingCountries()`, `setCustomScopes()` and `setHostName()`. The settings are public properties now.
- Removed the `craft\shopify\controllers` and `craft\shopify\console\controllers` classes.
- Removed the `craft\shopify\migrations` classes.
- Removed `craft\shopify\events\DefineGqlFieldsEvent`.
- Removed `craft\shopify\events\DefineGqlQueryArgumentsEvent`.
- Removed `craft\shopify\events\ShopifyProductSyncEvent`.
- Removed `craft\shopify\services\Api::EVENT_DEFINE_GQL_QUERY_ARGUMENTS`.
- Removed `craft\shopify\services\Api::EVENT_DEFINE_PRODUCT_GQL_FIELDS`.
- Removed `craft\shopify\services\Products::EVENT_BEFORE_SYNCHRONIZE_PRODUCT`.
- `CraftCms\Shopify\Product\Queries\ProductQuery::statusCondition()` now returns a `Closure`.
- `craft\shopify\gql\types\generators\ProductType::generateTypes()` now returns `GraphQL\Type\Definition\ObjectType` objects.
- `CraftCms\Shopify\BulkOperation\BulkOperations::queueNextBulkOperation()` no longer returns `false` when the processing job can’t be queued.
- Removed `craft\shopify\elements\Product::init()`, `prepareEditScreen()`, `getSidebarHtml()`, `hasContent()`, `canView()`, `canSave()`, `canDelete()` and `canCreateDrafts()`.
- Removed `craft\shopify\elements\Product::searchableAttributes()`. `CraftCms\Shopify\Product\Elements\Product::defineSearchableAttributes()` is overridden instead.
- Removed `craft\shopify\elements\db\ProductQuery::beforePrepare()`.
- Removed `craft\shopify\elements\db\ProductQuery::populate()`. Shopify data is now eager-loaded from `afterHydrate()`.
- Renamed `craft\shopify\elements\Product` to `CraftCms\Shopify\Product\Elements\Product`, which now extends `CraftCms\Cms\Element\Element`. The old class name is a deprecated alias.
- Renamed `craft\shopify\elements\db\ProductQuery` to `CraftCms\Shopify\Product\Queries\ProductQuery`, which now extends `CraftCms\Cms\Element\Queries\ElementQuery`. The old class name is a deprecated alias.
- Renamed the `craft\shopify\elements\conditions\products` classes to `CraftCms\Shopify\Product\Conditions`. The old class names are deprecated aliases.
- Renamed `craft\shopify\helpers\Product` to `CraftCms\Shopify\Helpers\Product`. The old class name is a deprecated alias.
- Added `CraftCms\Shopify\Helpers\Product::metadata()`, which replaces `renderCardHtml()`.
- Added `CraftCms\Shopify\Product\Policies\ProductPolicy`.
- Added `CraftCms\Shopify\Product\Elements\Product::$syncing`.
- Added `CraftCms\Shopify\Product\Queries\ProductQuery::applyHandle()`, `applyProductType()`, `applyShopifyStatus()`, `applyTags()`, `applyTemplateSuffix()` and `applyVendor()`.
- The `ProductQuery` constructor no longer accepts an element type.
- Product condition rules now implement `CraftCms\Cms\Element\Conditions\Contracts\ElementQueryConditionRuleInterface`.
- `CraftCms\Shopify\Product\Elements\Product::getShopifyLink()` now returns an `Illuminate\Support\HtmlString` object.
