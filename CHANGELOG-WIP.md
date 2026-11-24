# Release Notes for Shopify 9.0 (WIP)

> [!IMPORTANT]
> Shopify 9.0 requires Craft CMS 6 and PHP 8.5. See [UPGRADE.md](UPGRADE.md) for upgrade steps and breaking changes.

### System

- Shopify now requires Craft CMS 6.0 or later.
- Shopify now requires PHP 8.5 or later.
- Shopify now requires `craftcms/yii2-adapter`.
- Console commands are now Artisan commands, such as `php artisan shopify:sync:products`. The old command names still work as aliases.
- Shopify 9.0 can only be installed over Shopify 8.2.1 or later.
- Shopify’s config file is now `config/craft/shopify.php`.

### Extensibility

- Renamed `craft\shopify\Plugin` to `CraftCms\Shopify\Plugin`. The old class name is a deprecated alias.
- Renamed `craft\shopify\models\Settings` to `CraftCms\Shopify\Settings`, which now extends `CraftCms\Cms\Plugin\PluginSettings`. The old class name is a deprecated alias.
- Added `CraftCms\Shopify\Database\Table`.
- Removed `craft\shopify\models\Settings::setAdditionalFeatures()`, `setApiVersion()`, `setClientId()`, `setClientSecret()`, `setContextualPricingCountries()`, `setCustomScopes()` and `setHostName()`. The settings are public properties now.
- Removed the `craft\shopify\controllers` and `craft\shopify\console\controllers` classes.
- Removed the `craft\shopify\migrations` classes.
- `craft\shopify\elements\Product::prepareEditScreen()` now accepts a `Symfony\Component\HttpFoundation\Response` or `CraftCms\Cms\Http\Responses\CpScreenResponse` object.
- `craft\shopify\elements\db\ProductQuery::statusCondition()` now returns a `Closure`.
- `craft\shopify\gql\types\generators\ProductType::generateTypes()` now returns `GraphQL\Type\Definition\ObjectType` objects.
- `craft\shopify\services\BulkOperations::queueNextBulkOperation()` no longer returns `false` when the processing job can’t be queued.
- Removed `craft\shopify\elements\Product::init()`.
- Removed `craft\shopify\elements\Product::searchableAttributes()`. `craft\shopify\elements\Product::defineSearchableAttributes()` is overridden instead.
- Removed `craft\shopify\elements\db\ProductQuery::beforePrepare()`.
- Removed `craft\shopify\elements\db\ProductQuery::populate()`. Shopify data is now eager-loaded from `afterHydrate()`.
