# Release Notes for Shopify 9.0 (WIP)

> [!IMPORTANT]
> Shopify 9.0 requires Craft CMS 6 and PHP 8.5. See [UPGRADE.md](UPGRADE.md) for upgrade steps and breaking changes.

### System

- Shopify now requires Craft CMS 6.0 or later.
- Shopify now requires PHP 8.5 or later.
- Shopify now requires `craftcms/yii2-adapter`.
- Console commands are now run with `php artisan`, such as `php artisan craft:shopify:sync:products`.

### Extensibility

- `craft\shopify\elements\Product::prepareEditScreen()` now accepts a `Symfony\Component\HttpFoundation\Response` or `CraftCms\Cms\Http\Responses\CpScreenResponse` object.
- `craft\shopify\elements\db\ProductQuery::statusCondition()` now returns a `Closure`.
- `craft\shopify\gql\types\generators\ProductType::generateTypes()` now returns `GraphQL\Type\Definition\ObjectType` objects.
- `craft\shopify\services\BulkOperations::queueNextBulkOperation()` no longer returns `false` when the processing job can’t be queued.
- Removed `craft\shopify\elements\Product::init()`.
- Removed `craft\shopify\elements\Product::searchableAttributes()`. `craft\shopify\elements\Product::defineSearchableAttributes()` is overridden instead.
- Removed `craft\shopify\elements\db\ProductQuery::beforePrepare()`.
- Removed `craft\shopify\elements\db\ProductQuery::populate()`. Shopify data is now eager-loaded from `afterHydrate()`.
