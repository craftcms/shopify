<p align="center"><img src="./src/icon.svg" width="100" height="100" alt="Shopify icon"></p>

<h1 align="center">Shopify for Craft CMS</h1>

Connect your [Craft CMS](https://craftcms.com/) site to a [Shopify](https://shopify.com) store and keep your products in sync.

## Requirements

The Shopify plugin requires Craft CMS 4.0.0 or later.

## Installation

To install the plugin, visit the [Plugin Store](https://plugins.craftcms.com/shopify) from your Craft project, or follow these instructions.

1. Open your terminal and go to your Craft project:

    ```bash
    cd /path/to/project
    ```

2. Require the package with Composer:

    ```bash
    composer require craftcms/shopify -w
    ```

3. In the Control Panel, go to **Settings** → **Plugins** and click the “Install” button for Shopify, or run:

    ```bash
    php craft plugin/install shopify
    ```

### Create a Shopify App

The plugin works with Shopify’s [Custom Apps](https://help.shopify.com/en/manual/apps/custom-apps) system.

> If you are not the owner of the Shopify store, have the owner add you as a collaborator or staff member with the [_Develop Apps_ permission](https://help.shopify.com/en/manual/apps/custom-apps#api-scope-permissions-for-custom-apps).

Follow [Shopify’s directions](https://help.shopify.com/en/manual/apps/custom-apps) for creating a private app (through the _Get the API credentials for a custom app_ section), and provide these details when asked:

1. **App Name**: Choose something that identifies the integration, like “Craft CMS.”
2. **Admin API access scopes**: The following scopes are required for the plugin to function correctly:
    - `read_products`
    - `write_product_listings`
    - `read_product_listings`

    Additionally (at the bottom of this screen), the **Webhook subscriptions** &rarr; **Event version** should be `2022-04`.
3. **Admin API access token**: Reveal and copy this value into your `.env` file, as `SHOPIFY_ADMIN_ACCESS_TOKEN`.
4. **API key and secret key**: Reveal and/or copy the **API key** and **API secret key** into your `.env` under `SHOPIFY_API_KEY` and `SHOPIFY_API_SECRET_KEY`, respectively.

#### Store Hostname

The last piece of info you’ll need on hand is your store’s hostname. This is usually what appears in the browser when using the Shopify admin—it also appears in the Settings screen of your store:

<img src="./docs/shopify-hostname.png" alt="Screenshot of the settings screen in the Shopify admin, with an arrow pointing to the store’s default hostname in the sidebar.">

Save this value (_without_ the leading `http://` or `https://`) in your `.env` as `SHOPIFY_HOSTNAME`. At this point, you should have four Shopify-specific values:

```env
# ...

SHOPIFY_ADMIN_ACCESS_TOKEN="..."
SHOPIFY_API_KEY="..."
SHOPIFY_API_SECRET_KEY="..."
SHOPIFY_HOSTNAME="my-storefront.myshopify.com"
```

### Connect Plugin

Now that you have credentials for your custom app, it’s time to add them to Craft.

1. Visit **Shopify** &rarr; **Settings** screen in your project’s control panel.
2. Assign the four environment variables to the corresponding settings, using the special [config syntax](https://craftcms.com/docs/4.x/config/#control-panel-settings):
    - **API Key**: `$SHOPIFY_API_KEY`
    - **API Secret Key**: `$SHOPIFY_API_SECRET_KEY`
    - **Access Token**: `$SHOPIFY_ACCESS_TOKEN`
    - **Host Name**: `$SHOPIFY_HOSTNAME`
3. Click **Save**.

### Set up Webhooks

Once your credentials have been added to Craft, a new **Webhooks** tab will appear in the **Shopify** section of the control panel.

Click **Generate** on the Webhooks screen to add the required webhooks to Shopify. The plugin will use the credentials you just configured to perform this operation—so this also serves as an initial communication test.

> :rotating_light: You will need to add webhooks for each environment you deploy the plugin to, because each webhook is tied to a specific URL.

> :bulb: If you need to test live synchronization in development, we recommend using the [ngrok](https://ngrok.com/) tool to create a tunnel to your local environment. DDEV makes this simple, with [the `ddev share` command](https://ddev.readthedocs.io/en/latest/users/topics/sharing/). Keep in mind that your site’s primary/base URL is used when registering webhooks, so you may need to update it to match the ngrok tunnel.

## Product Element

Products from your Shopify store are represented in Craft as product [elements](https://craftcms.com/docs/4.x/elements.html), and can be found by going to **Shopify** &rarr; **Products** in the control panel.

### Synchronization

Products will be automatically created, updated, and deleted via [webhooks](#set-up-webhooks)—but Craft doesn’t know about a product until a change happens.

Once the plugin has been configured, perform an initial synchronization via the command line:

    php craft shopify/sync/products

> :bulb: Products can also be synchronized from the control panel using the **Shopify Sync** utility. Keep in mind that large stores (over a hundred products) may take some time to synchronize, and can quickly run through [PHP’s `max_execution_time`](https://www.php.net/manual/en/info.configuration.php#ini.max-execution-time).

### Native Attributes

In addition to the standard element fields like `id`, `title`, and `status`, each Shopify product element contains the following mappings to its canonical [Shopify Produce resource](https://shopify.dev/api/admin-rest/2022-10/resources/product#resource-object)

Attribute | Description | Type
--------- | ----------- | ----
`shopifyId` | The unique product identifier in your Shopify store. | `String`
`shopifyStatus` | The status of the product in your Shopify store. Values can be `active`, `draft`, or `archived`. | `String`
`handle` | The product’s “URL handle” in Shopify, equivalent to a “slug” in Craft. For existing products, this is visible under the **Search engine listing** section of the edit screen. | `String`
`productType` | The product type of the product in your Shopify store. | `String`
`bodyHtml` | Product description. Use the `\|raw` filter to output it in Twig—but only if the content is trusted. | `String`
`publishedScope` | Published scope of the product in Shopify store. Common values are `web` (for web-only products) and `global` (for web and point-of-sale products). | `String`
`tags` | Tags associated with the product in Shopify. | `Array`
`templateSuffix` | The suffix of the Liquid template used for the product page in Shopify. | `String`
`vendor` | Vendor of the product. | `String`
`images` | Images attached to the product in Shopify. The complete [Product Image resources](https://shopify.dev/api/admin-rest/2022-10/resources/product-image#resource-object) are stored in Craft. | `Array`
`options` | Product options, as configured in Shopify. Each option has a `name`, `position`, and an array of `values`. | `Array`
`createdAt` | When the product was created in your Shopify store. | `DateTime`
`publishedAt` | When the product was published in your Shopify store. | `DateTime`
`updatedAt` | When the product was last updated in your Shopify store. | `DateTime`

> :bulb: See the Shopify documentation on the [product resource](https://shopify.dev/api/admin-rest/2022-04/resources/product#resource-object) for more information about what kinds of values to expect from these properties.

### Custom Fields

Products synchronized from Shopify have a dedicated field layout, which means they support Craft’s full array of content tools.

The product field layout can be edited by going to **Shopify** &rarr; **Settings** &rarr; **Products**, and scrolling down to **Field Layout**.

### Product Status

A product’s `status` in Craft is a combination of its `shopifyStatus` attribute ('active', 'draft', or 'archived') and its enabled state. The former can only be changed from Shopify; the latter is set in the Craft control panel.

> :information_desk_person: Statuses in Craft are often a synthesis of multiple properties. For example, entries can be _Pending_—but this is just a simple value that stands in for a combination of being `enabled` _and_ having a `postDate` in the future.

In most cases, you’ll only need to display “Live” products, or those which are _Active_ in Shopify and _Enabled_ in Craft:

Status | Shopify | Craft
------ | ------- | -----
`live` | Active | Enabled
`shopifyDraft` | Draft | Enabled
`shopifyArchived` | Archived | Enabled
`disabled` | Any | Disabled

## Querying Products

Products can be queried like any other element in the system.

A new query begins with the `craft.shopifyProducts` factory function:

```twig
{% set products = craft.shopifyProducts.all() %}
```

### Query Parameters

The following query parameters are supported:

#### `shopifyId`

One or multiple Shopify product IDs to filter by.

```twig
{# Watch out—these aren't the same as element IDs! #}
{% set singleProduct = craft.shopifyProducts
    .shopifyId(123456789)
    .one() %}
```

#### `shopifyStatus`

Directly query against the product’s status in Shopify.

```twig
{% set archivedProducts = craft.shopifyProducts
    .shopifyStatus('archived')
    .all() %}
```

Use the regular `.status()` param if you'd prefer to query against [synthesized status values](#product-status).

#### `handle`

```twig
{# Todo #}
```

#### `productType`

```twig
{# Todo #}
```

#### `publishedScope`

```twig
{# Todo #}
```

#### `tags`

```twig
{# Todo: JSON blob? #}
```

#### `vendor`

```twig
{# Todo #}
```

#### `images`

```twig
{# Todo: JSON blob? #}
```

#### `options`

```twig
{# Todo: JSON blob? #}
```

## Product Field

The plugin provides a _Shopify Products_ field, which uses the familiar [relational field](https://craftcms.com/docs/4.x/relations.html) UI to allow authors to select Product elements.

# Migrating from v2.x of the plugin

You can remove the old plugin from your composer.json but do not uninstall it.

If you used the old product field, after upgrading you will see a 'missing field' in your field layouts.

To migrate to a new field:

1. Add the new 'Shopify Product' field to your field layout with a different field name. 
2. Run the below command to resave the data from the old field to the new field.

Note: Replace the section handle and field names with your own below

`blog` should be entry section you used.
`oldShopifyField` is the field handle from the previous version of the plugin
`shopifyProductsRelatedField` is the new field handle for the standard product relation field 

```bash
php craft resave/entries --section=blog --set shopifyProductsRelatedField --to "fn(\$entry) => collect(json_decode(\$entry->oldShopifyField))->map(fn (\$item) => \craft\shopify\Plugin::getInstance()->getProducts()->getProductIdByShopifyId(\$item))->unique()->all()"
```

After making the data migration, you can access the new field in your templates like this:

```twig
{% set products = entry.shopifyProductsRelatedField.all() %}
{% for product in products %}
    {{ product.handle }}
{% endfor %}
```

There is no longer the need to make an API call to Shopify to get the product data. The data is now stored in the Craft product element.
