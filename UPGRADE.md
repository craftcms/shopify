# Upgrading to Shopify 9.0 (Craft 6)

This guide lists what changed in Shopify 9.0 and what you need to update in your project, templates and custom code.

## Requirements

- Craft CMS 6.0 or later
- PHP 8.5 or later
- `craftcms/yii2-adapter`, which Shopify 9.0 requires and Composer installs for you

## Upgrade steps

1. Make sure the Craft queue is empty, so no Shopify sync jobs are pending.
2. Upgrade Craft and Shopify together, following [Upgrading to Craft 6.x](https://craftcms.com/docs/6.x/upgrade.html). Shopify 8 requires Craft 5 and Shopify 9 requires Craft 6, so change both constraints in your project's `composer.json` before you run `composer update`: set `craftcms/shopify` to `^9.0` at the same point you set `craftcms/cms` to Craft 6. The `craft6-revamp` tool doesn't change plugin constraints for you.
3. Run `php artisan craft:up`.
4. Update cron jobs and deploy scripts that run Shopify console commands (see below).
5. Review the breaking changes below and update your code.

The webhook URL (`actions/shopify/webhook/handle`) and the OAuth callback URL (`{cpTrigger}/shopify/auth`) are unchanged, so your Shopify app configuration doesn't need updating.

## Breaking changes

### Console commands

Console commands run through Artisan, and use colons between every segment.

| Before | After |
| --- | --- |
| `php craft shopify/sync/products` | `php artisan craft:shopify:sync:products` |
| `php craft shopify/sync/all` | `php artisan craft:shopify:sync:all` |
| `php craft shopify/api/query` | `php artisan craft:shopify:api:query` |
| `php craft shopify/data/reset` | `php artisan craft:shopify:data:reset` |

### Product element and query

These only affect custom code that extends `craft\shopify\elements\Product` or `craft\shopify\elements\db\ProductQuery`.

| Before | After |
| --- | --- |
| `Product::searchableAttributes()` | `Product::defineSearchableAttributes()` (protected) |
| `Product::init()` | `Product::__construct()` |
| `Product::prepareEditScreen(yii\web\Response $response, …)` | `Product::prepareEditScreen(Symfony\Component\HttpFoundation\Response\|CraftCms\Cms\Http\Responses\CpScreenResponse $response, …)` |
| `ProductQuery::beforePrepare()` | Removed. The query joins its tables in the constructor and applies its params in a `beforeQuery()` callback. |
| `ProductQuery::populate()` | Removed. Shopify data is eager-loaded in `ProductQuery::afterHydrate()`. |
| `ProductQuery::statusCondition()` returning a condition array | `ProductQuery::statusCondition()` returning a `Closure` that modifies the query builder |

### GraphQL

`craft\shopify\gql\types\generators\ProductType::generateTypes()` returns `GraphQL\Type\Definition\ObjectType` objects instead of `craft\gql\base\ObjectType` objects. The GraphQL schema itself is unchanged.

### Feed Me

The Feed Me integration is untested on Craft 6, and will be updated once Feed Me supports Craft 6.

## Need help?

Open an issue at https://github.com/craftcms/shopify/issues.
