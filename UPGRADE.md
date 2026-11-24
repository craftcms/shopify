# Upgrading to Shopify 9.0 (Craft 6)

This guide lists what changed in Shopify 9.0 and what you need to update in your project, templates and custom code.

## Requirements

- Craft CMS 6.0 or later
- PHP 8.5 or later
- `craftcms/yii2-adapter`, which Shopify 9.0 requires and Composer installs for you

## Upgrade steps

1. Update Shopify to 8.2.1 or later on Craft 5, and run its migrations.
2. Make sure the Craft queue is empty, so no Shopify sync jobs are pending.
3. Upgrade Craft and Shopify together, following [Upgrading to Craft 6.x](https://craftcms.com/docs/6.x/upgrade.html). Shopify 8 requires Craft 5 and Shopify 9 requires Craft 6, so change both constraints in your project's `composer.json` before you run `composer update`: set `craftcms/shopify` to `^9.0` at the same point you set `craftcms/cms` to Craft 6. The `craft6-revamp` tool doesn't change plugin constraints for you.
4. Run `php artisan craft:up`.
5. If Shopify kept its access token in the database rather than in your `.env` file, re-authorize the app (see [Access token](#access-token)).
6. Move `config/shopify.php` to `config/craft/shopify.php`, if you have one.
7. Update cron jobs and deploy scripts that run Shopify console commands (see below).
8. Review the breaking changes below and update your code.

The webhook URLs (`actions/shopify/webhook/handle` and `{cpTrigger}/actions/shopify/webhook/handle`) and the OAuth callback URL (`{cpTrigger}/shopify/auth`) are unchanged, so your Shopify app configuration doesn't need updating.

## Breaking changes

### Access token

When Shopify 8 authorizes the app, it saves the access token to the `SHOPIFY_API_ACCESS_TOKEN` variable in your `.env` file. If it can't write to the file, it stores the token in the `shopify_accesstokens` table instead, encrypted with Craft 5's security key. Craft 6 encrypts data with `APP_KEY` instead, so it can't decrypt that token.

If the `accessToken` column in `shopify_accesstokens` holds `$SHOPIFY_API_ACCESS_TOKEN`, nothing changes. Otherwise, the Shopify settings screen says "Unable to connect to custom app" after the upgrade, and syncing and webhooks stop until you re-authorize:

1. Open the app from the **Apps** section of your store's Shopify admin. Shopify sends you to the app's auth URL in the Craft control panel.
2. Press **Authorize**.

See [Install in a Store](README.md#install-in-a-store) for the full flow. The new token is saved the same way as before.

### Console commands

Console commands are Artisan commands now. The old command names still work as aliases, but cron jobs and deploy scripts should switch to the new names.

| Before | After |
| --- | --- |
| `php craft shopify/sync/products` | `php artisan shopify:sync:products` |
| `php craft shopify/sync/all` | `php artisan shopify:sync:all` |
| `php craft shopify/api/query` | `php artisan shopify:api:query` |
| `php craft shopify/data/reset` | `php artisan shopify:data:reset` |
| `php craft resave/shopify-products` | `php artisan craft:resave:shopify-products` |

### Config file

Move `config/shopify.php` to `config/craft/shopify.php`. Use `env()` instead of `craft\helpers\App::env()` to read environment variables in it.

### Plugin and settings classes

| Before | After |
| --- | --- |
| `craft\shopify\Plugin` | `CraftCms\Shopify\Plugin` |
| `craft\shopify\models\Settings` | `CraftCms\Shopify\Settings` |

The old class names still work as aliases, but are deprecated.

`CraftCms\Shopify\Settings` extends `CraftCms\Cms\Plugin\PluginSettings`. Settings that can reference environment variables are public properties that hold the raw value, such as `$SHOPIFY_CLIENT_ID`. Their getters return the parsed value. These setters were removed, so assign the property instead:

- `setApiVersion()`
- `setClientId()`
- `setClientSecret()`
- `setHostName()`
- `setAdditionalFeatures()`
- `setCustomScopes()`
- `setContextualPricingCountries()`

The plugin's services (`Api`, `BulkOperations`, `Products` and `Store`) are container singletons. `Plugin::getInstance()->getApi()` and the other getters still return them.

### Controllers

The `craft\shopify\controllers\*` and `craft\shopify\console\controllers\*` classes were removed. The control panel controllers are in `CraftCms\Shopify\Http\Controllers`, and the console commands are in `CraftCms\Shopify\Console\Commands`. The control panel and action URLs are unchanged.

### Migrations

The `craft\shopify\migrations\*` classes were removed. To upgrade to Shopify 9.0, a project must first be on Shopify 8.2.1 or later.

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
