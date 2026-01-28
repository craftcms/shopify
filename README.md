<p align="center"><img src="./src/icon.svg" width="100" height="100" alt="Shopify icon"></p>

<h1 align="center">Shopify for Craft CMS</h1>

Build a content-driven storefront by synchronizing [Shopify](https://shopify.com) products into [Craft CMS](https://craftcms.com/).

> [!IMPORTANT]
> Version 7.x of the Shopify plugin uses new app-based authorization.
> Existing integrations and credentials should continue to work with no changes, but the process for creating _new_ credentials has changed significantly in Shopify.

## Topics

- :package: [Installation](#installation): Set up the plugin and get connected to Shopify.
- :card_file_box: [Working with Products](#product-element): Learn what kind of data is available and how to access it.
- :bookmark_tabs: [Templating](#templating): Tips and tricks for using products in Twig.
- :leaves: [Upgrading](#upgrading): Take advantage of new features and performance improvements.
- :telescope: [Advanced Features](#going-further): Go further with your integration.

## Installation

Shopify requires Craft CMS 4.15.0+ or 5.0.0+.

To install the plugin, visit the [Plugin Store](https://plugins.craftcms.com/shopify) from your Craft project, or follow these instructions.

1. Navigate to your Craft project in a new terminal:

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

### Connect to Shopify

The plugin works with Shopify’s [Dev Dashboard](https://shopify.dev/docs/apps/build/dev-dashboard) app system, and is split into three parts: [creating an app](#create-an-app), [installing it into a store](#install-in-a-store), and finally [connecting it to Craft](#connect-to-craft).

> [!CAUTION]
> The following process may differ significantly if you are part of a [Partner](https://www.shopify.com/partners) organization or are a collaborator on multiple stores.
> Shopify implicitly creates an “organization” for each store, and that organization gets a dedicated Dev Dashboard.
> **You must access the Dev Dashboard via the store you want to create an app for, _not_ via a link in the documentation or by directly navigating to its URL!**

To perform these steps, you must either be the owner of a store, or a collaborator with the [App developer role](https://shopify.dev/docs/apps/build/dev-dashboard/user-permissions).

![Adding a collaborator via the Shopify admin](docs/shopify-add-collaborator.png)

#### Create an App

1. From a store, open **Settings** &rarr; **Apps**, press **Develop apps** in the toolbar, then follow the **Build apps in Dev Dashboard** link.
1. In the Dev Dashboard, press **Create app**.
2. In the first screen, pick an **App name** that identifies the integration, like _Craft CMS_.
1. Press **Create**, then fill out the following fields to create your first “version”:

  - **App URL**: (Optional) Replace `example.com` with `https://shopify.dev/apps/default-app-home`. This is just a friendlier (but still somewhat confusing) page hosted by Shopify, displayed when your “app” is accessed in the Shopify store’s admin. (If you turn off **Embed app in Shopify admin**, you are redirected to this URL after [installing](#install-in-a-store) the app. Neither is particularly useful; this is a symptom of Shopify’s lack of an API-only integration path.)
  - **Webhooks API Version**: Choose `2025-10`, and add the same string to your project’s `.env` file:
    ```bash
    SHOPIFY_WEBHOOK_VERSION="2025-10"
    ```
  - **Access** &rarr; **Scopes**: The following scopes are required for the plugin to function correctly:
    - `read_inventory`
    - `read_product_listings`
    - `read_products`
    - `unauthenticated_read_product_listings`
    - Shopify requires these to be in a comma-separated list: `read_inventory,read_product_listings,read_products,unauthenticated_read_product_listings`
  - Do _not_ enable the **Use legacy install flow** as it can result in mismatched scopes during installation.
1. Press **Release** to deploy the configuration. You may give it a name and description, or let Shopify tag it with an incrementing number.
1. Switch to the **Settings** screen of the new app, and copy the credentials into your `.env` file:
  ```bash
  SHOPIFY_CLIENT_ID="..." # Client ID
  SHOPIFY_CLIENT_SECRET="..." # Secret
  ```

#### Install in a Store

> [!WARNING]
> If you are not the owner of the Shopify store, have the owner add you as a [collaborator](https://help.shopify.com/en/manual/your-account/users/security/collaborator-accounts).

From your new app’s **Home** screen, press **Install**. A new window will open with the store selector; pick the store you started from. _Your app can only be installed in the store associated with the Dev Dashboard it was created in._

> [!TIP]
> You may see a warning like “This app hasn't been reviewed.”
> This is to be expected; your app is not publicly available, and doesn’t need to go through the normal Marketplace approval process.
> 
> If you see “This app can't be installed on this store,” it was probably created from the wrong Dev Dashboard, and you’ll need to [start over](#create-an-app).

Press **Install** on this screen.
When Shopify redirects to the generic embedded app view, you’re done!

### Connect Plugin

We still need one piece of information from your store—the hostname, or “domain.”
Visit the **Settings** screen in the Shopify admin, and copy this value from the sidebar:

![Adding a collaborator via the Shopify admin](docs/shopify-hostname.png)

Add this to your `.env`, without any `https://` prefix:

```bash
SHOPIFY_HOSTNAME="rpfxyv-fk.myshopify.com"
```

You should now have a total of _four_ `SHOPIFY_*` variables in your `.env` file.
In your Craft project’s control panel, navigate to **Shopify** &rarr; **Settings** to configure the plugin:

- **API Version**: `$SHOPIFY_WEBHOOKS_VERSION`
- **API Key**: `$SHOPIFY_CLIENT_ID`
- **API Secret Key**: `$SHOPIFY_CLIENT_SECRET`
- **Host Name**: `$SHOPIFY_HOSTNAME`

Save the settings to test the connection; an exception will be thrown if there are issues.

> [!TIP]
> The temporary session token from Shopify is cached for its expected duration.
> This _can_ obscure connection issues after changing credentials, so it’s always a good idea to run `craft clear-caches/all`, beforehand. 

### Set up Webhooks

Once your credentials have been added to Craft, a new **Webhooks** tab will appear in the **Shopify** section of the control panel.

Click **Create webhooks** on the Webhooks screen to add the required webhooks to Shopify. The plugin will use the credentials you just configured to perform this operation—so this also serves as an initial communication test.

> [!WARNING]
> You must add webhooks for every environment you deploy the plugin to; webhooks are tied to the specific, registered URL.
> Be aware that Shopify will continue to attempt delivery to your development webhooks, which may impact the statistics you see in the Dev Dashboard.

> [!NOTE]
> If you need to test synchronization in development, we recommend using [ngrok](https://ngrok.com/) to create a tunnel to your local environment.
> DDEV makes this simple, with [the `ddev share` command](https://ddev.readthedocs.io/en/latest/users/topics/sharing/). 
> Use the `SHOPIFY_WEBHOOKS_BASE_URL` environment variable to override the base URL used when generating webhook URLs; this allows you to continue using your regular DDEV site URL for control panel and front-end access, rather than overriding the entire project or site’s base URL.
> This setting may not work if you have set a custom `cpBaseUrl`!

## Upgrading

This release (7.x) is primarily concerned with Shopify API compatability.

> [!TIP]
> We strongly recommend reviewing this same section on the [6.x](https://github.com/craftcms/shopify/blob/6.x/README.md#upgrading) branch, as there were a number of breaking changes and deprecations during the upgrade from 5.x.

After the upgrade, you **must**:

1. Update the webhook version setting to `2025-10` in your app _and_ Craft project
1. Review the required [access scopes](#create-an-app)
1. Delete and re-create webhooks for each environment (This is essential! Webhooks are registered and delivered with a specific version, and a mismatch will result in errors.)

This ensures that the plugin can properly communicate with the Shopify API.
If you elect to migrate to the Dev Dashboard during the upgrade, you can leave your “legacy custom app” configuration as-is.

### Credentials

At the beginning of 2026, Shopify overhauled how “apps” are created, moving them to the new [Dev Dashboard](https://shopify.dev/docs/apps/build/dev-dashboard).
_Your existing credentials will continue to work_, but you may find that some features (like webhook delivery logs) are worth making the transition.

You should be able to [create a new app](#create-an-app), [install it](#install-in-a-store), and [replace credentials](#connect-to-shopify) without disruption.

## Product Element

Products from your Shopify store are represented in Craft as product [elements](https://craftcms.com/docs/5.x/system/elements.html), and can be found by going to **Shopify** → **Products** in the control panel.

### Synchronization

Once the plugin has been configured, you can perform an initial synchronization of all products via the control panel (via **Utilities** &rarr; **Shopify Sync**) or the command line:

```sh
php craft shopify/sync/products
```

This adds a [bulk operation](https://shopify.dev/docs/api/usage/bulk-operations/queries) to the plugin’s internal queue. Once Shopify has gathered the data, it will issue a webhook to your project, and the plugin will download and process the payload.

Going forward, your products are automatically kept in sync via [webhooks](#set-up-webhooks). You can view a history of synchronization operations by visiting the **Shopify Sync** utility.

### Native Attributes

In addition to the standard element attributes like `id`, `title`, and `status`, each Shopify product element contains direct accessors for these canonical Shopify [Product attributes](https://shopify.dev/docs/api/admin-graphql/2025-07/objects/Product):

| Attribute         | Description                                                                                                                                                                                        | Type       |
|-------------------|----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------| ---------- |
| `shopifyId`       | The unique product [identifier](https://shopify.dev/docs/api/admin-graphql/latest/scalars/ID) in your Shopify store.                                                                               | `String`   |
| `shopifyStatus`   | The status of the product in your Shopify store. Values can be `active`, `draft`, or `archived`.                                                                                                   | `String`   |
| `handle`          | The product’s “URL handle” in Shopify, equivalent to a “slug” in Craft. For existing products, this is visible under the **Search engine listing** section of the edit screen.                     | `String`   |
| `productType`     | The product type of the product in your Shopify store.                                                                                                                                             | `String`   |
| `descriptionHtml` | Product description. Use the `\|raw` filter to output it in Twig—but only if the content is trusted. This was previously called `bodyHtml`.                                                        | `String`   |
| `tags`            | Tags associated with the product in Shopify.                                                                                                                                                       | `Array`    |
| `templateSuffix`  | [Liquid template suffix](https://shopify.dev/themes/architecture/templates#name-structure) used for the product page in Shopify.                                                                   | `String`   |
| `vendor`          | Vendor of the product.                                                                                                                                                                             | `String`   |
| `metaFields`      | [Metafields](https://shopify.dev/docs/api/admin-graphql/latest/objects/Metafield) associated with the product.                                                                                     | `Array`    |
| `images`          | Images attached to the product in Shopify. The complete [ProductImage resources](https://shopify.dev/docs/api/admin-graphql/latest/objects/MediaImage) are stored in Craft.                        | `Array`    |
| `options`         | [ProductOption](https://shopify.dev/docs/api/admin-graphql/latest/objects/ProductOption) objects, as configured in Shopify. Each option has a `name`, `position`, and an array of in-use `values`. | `Array`    |
| `createdAt`       | When the product was created in your Shopify store. (This will almost always be different from the element’s native `dateCreated` property.)                                                       | `DateTime` |
| `publishedAt`     | When the product was published in your Shopify store.                                                                                                                                              | `DateTime` |
| `updatedAt`       | When the product was last updated in your Shopify store. (This will almost always be different from the element’s native `dateUpdated` property.)                                                  | `DateTime` |

All of these properties are available when working with a product element [in your templates](#templating).

> [!IMPORTANT]  
> See the Shopify documentation on the [product resource](https://shopify.dev/docs/api/admin-graphql/latest/objects/Product) for more information about what kinds of values to expect from these properties.

A complete copy of the Shopify API data used to populate a product element is available under its `data` property. Wherever possible, we have used Shopify’s native property names—but by virtue of fetching products via GraphQL, there may be differences between the structure of this object and the API documentation, especially as it relates to nested objects. Use the following [methods](#methods) to access related or nested data!

### Methods

The product element has a few methods you might find useful in your [templates](#templating).

#### `Product::getVariants()`

Returns an array of [variants](#variants-and-pricing) belonging to the product. Each variant is an associative array—_not_ an element—but you can use the same dot notation to access their properties:

```twig
{% set variants = product.getVariants() %}

<select name="variantId">
  {% for variant in variants %}
    <option value="{{ variant.id }}">{{ variant.title }}</option>
  {% endfor %}
</select>
```

You can [eager-load](#eager-loading) variants alongside products using the [product query](#querying-products)’s `.withVariants()` method.

#### `Product::getDefaultVariant()`

Shortcut for getting the first/default [variant](#variants-and-pricing) belonging to the product.

```twig
{% set products = craft.shopifyProducts
   .withVariants()
   .all() %}

<ul>
  {% for product in products %}
    {% set defaultVariant = product.getDefaultVariant() %}

    <li>
      <a href="{{ product.url }}">{{ product.title }}</a>
      <span>{{ defaultVariant.price|currency }}</span>
    </li>
  {% endfor %}
</ul>
```

#### `Product::getCheapestVariant()`

Shortcut for getting the lowest-priced [variant](#variants-and-pricing) belonging to the product.

```twig
{% set cheapestVariant = product.getCheapestVariant() %}

Starting at {{ cheapestVariant.price|currency }}!
```

Note that this does not factor in [contextual pricing](#contextual-pricing).

#### `Product::getShopifyUrl()`

```twig
{# Get a link to the product’s page on Shopify: #}
<a href="{{ product.getShopifyUrl() }}">View on our store</a>

{# Link to a product with a specific variant pre-selected: #}
<a href="{{ product.getShopifyUrl({ variant: variant.id }) }}">Buy now</a>
```

This has limited utility if you are displaying products on-site (rather than linking back to a Shopify storefront). To get the URL of a product within your Craft project, use `product.url`.

#### `Product::getShopifyEditUrl()`

For administrators, you can even link directly to the Shopify admin:

```twig
{# Assuming you’ve created a custom group for Shopify admin: #}
{% if currentUser and currentUser.isInGroup('clerks') %}
  <a href="{{ product.getShopifyEditUrl() }}">Edit product on Shopify</a>
{% endif %}
```

### Custom Fields

Products synchronized from Shopify have a dedicated field layout, which means they support Craft’s full array of [content tools](https://craftcms.com/docs/5.x/system/fields.html).

The product field layout can be edited by going to **Shopify** → **Settings** → **Products**, and scrolling down to **Field Layout**.

Fields are accessible from any product element, by their handle:

```twig
{# Native properties: #}
<h2>{{ product.title }}</h2>
<span class="price">{{ product.price|currency }}</span>

{# Custom relational field: #}
<ul class="support">
  {% for article in product.relatedHelpArticles.all() %}
    <li>{{ article.getLink() }}</li>
  {% endfor %}
</ul>
```

Variants and other nested records do not support custom fields.

### Routing

You can give synchronized products their own on-site URLs. To set up the URI format (and the template that will be loaded when a product URL is requested), go to **Shopify** → **Settings** → **Products**.

If you would prefer your customers to view individual products on Shopify, clear out the **Product URI Format** field on the settings page, and use [`product.shopifyUrl`](#productgetshopifyurl) instead of `product.url` in your templates.

### Product Status

A product’s `status` in Craft is a combination of its `shopifyStatus` attribute ('active', 'draft', or 'archived') and its enabled state. The former can only be changed from Shopify; the latter is set in the Craft control panel.

> **Note**  
> Statuses in Craft are often a synthesis of multiple properties. For example, an entry with the _Pending_ status just means it is `enabled` _and_ has a `postDate` in the future.

In most cases, you’ll only want to display “Live” products, or those which are _Active_ in Shopify and _Enabled_ in Craft:

| Status            | Shopify  | Craft    |
| ----------------- | -------- | -------- |
| `live`            | Active   | Enabled  |
| `shopifyDraft`    | Draft    | Enabled  |
| `shopifyArchived` | Archived | Enabled  |
| `disabled`        | Any      | Disabled |

This is the default behavior when [querying](#querying-products) for products, but you can pass one of the custom **Status** options above to the `.status()` param to override it.

## Querying Products

Products can be queried like any other [element type](https://craftcms.com/docs/5.x/development/element-queries.html) in Craft.

A new query begins with the `craft.shopifyProducts` factory function:

```twig
{% set products = craft.shopifyProducts.all() %}
```

The plugin automatically loads the relevant product when its [route](#routing) is requested, and makes a `product` variable available in the template. You only need to query for products when when they are displayed outside of this context. [Product fields](#product-field) also return product queries.

### Query Parameters

The following element query parameters are supported, in addition to [Craft’s standard set](https://craftcms.com/docs/5.x/development/element-queries.html).

> [!NOTE]
> Fields stored as JSON (like [`tags`](#tags), [`options`](#options) and [`metafields`](#metafields)) are only queryable as plain text. If you need to do advanced organization or filtering, we recommend using custom Category or Tag fields in your Product [field layout](#custom-fields).

#### `shopifyId`

Filter by legacy numeric Shopify product IDs.

```twig
{# Watch out—these aren't the same as element IDs! #}
{% set singleProduct = craft.shopifyProducts
  .shopifyId(123456789)
  .one() %}
```

#### `shopifyGid`

Filter by Shopify GIDs.

```twig
{# Watch out—these aren't the same as element IDs! #}
{% set singleProduct = craft.shopifyProducts
  .shopifyId('gid://shopify/Product/123456789')
  .one() %}
```

This is equivalent to `.shopifyId(123456789)`, but may be simpler if you are combining data from client-side queries.

#### `shopifyStatus`

Directly query against the product’s status in Shopify.

```twig
{% set archivedProducts = craft.shopifyProducts
  .shopifyStatus('archived')
  .all() %}
```

Use the regular `.status()` param if you'd prefer to query against the [synthesized product status values](#product-status).

> [!WARNING]
> Note that `.shopifyStatus()` _does not_ override conditions applied by the `.status()` param (including the defaults). You may need to call `.status(null)` to unset them, or use `.status('shopifyDraft')`, directly.

#### `handle`

Query by the product’s handle, in Shopify.

```twig
{% set product = craft.shopifyProducts
  .handle('worlds-tallest-socks')
  .all() %}
```

> [!WARNING]
> This is _not_ a reliable means to fetch a specific product, as the value may change during a synchronization. If you want to store a permanent reference to a product, consider using the Shopify [product field](#product-field) to relate it by element ID.

#### `productType`

Find products by their “type” in Shopify.

```twig
{% set upSells = craft.shopifyProducts
  .productType(['apparel', 'accessories'])
  .all() %}
```

#### `tags`

Tags are stored as a JSON array, which may complicate direct comparisons. You may see better results using [the `.search()` param](https://craftcms.com/docs/5.x/system/searching.html#development).

```twig
{# Find products whose tags include the term in any position, with variations on casing: #}
{% set clogs = craft.shopifyProducts
  .tags(['*clog*', '*Clog*'])
  .all() %}
```

#### `options`

Options are stored as a JSON array, which may complicate direct comparisons. You may see better results using [the `.search()` param](https://craftcms.com/docs/5.x/system/searching.html#development).

```twig
{# Find products whose options include a `size` key: #}
{% set clogs = craft.shopifyProducts
  .tags('*"size"*')
  .all() %}
```

#### `vendor`

Filter by the vendor information from Shopify.

```twig
{# Find products with a vendor matching either option: #}
{% set fancyBags = craft.shopifyProducts
  .vendor(['Louis Vuitton', 'Jansport'])
  .all() %}
```

### Eager-loading

[Variants](#variants-and-pricing) (`ProductVariant`s), images (`MediaImage`s), and meta fields (`Metafield`s) attached to product elements are not elements themselves, and must be explicitly eager-loaded to avoid performance issues when displaying data in a loop:

```twig
{% set products = craft.shopifyProducts()
   .withVariants()
   .withImages()
   .withMetafields()
   .all() %}

<ul>
  {% for product in products %}
    <li>
      <h2>{{ product.title }}</h2>
      Available in {{ product.variants|column('title')|join(', ') }}.
      
      {# Similar loops for each type of nested record... #}
    </li>
  {% endfor %}
</ul>
```

> [!TIP]
> The shorthand `.withAll()` is a future-proof means of eager-loading each additional type of nested record.

You can still access `product.variants`, `product.images`, and `product.metafields` without eager-loading—but it may result in an additional query for each kind of content. Once you’ve retrieved variants, for example, they are memoized on the product element instance for the duration of the request.

## Templating

### Product Data

Products behave just like any other element, in Twig. Once you’ve loaded a product via a [query](#querying-products) (or have a reference to one on its template), you can output its native [Shopify attributes](#native-attributes) and [custom field](#custom-fields) data.

> [!NOTE]
> Some attributes are stored as JSON, which limits nested properties’s types. As a result, dates may be slightly more difficult to work with.

```twig
{# Standard element title: #}
{{ product.title }}
  {# -> Root Beer #}

{# Shopify HTML content: #}
{{ product.descriptionHtml|raw }}
  {# -> <p>...</p> #}

{# Tags, as list: #}
{{ product.tags|join(', ') }}
  {# -> sweet, spicy, herbal #}

{# Tags, as filter links: #}
{% for tag in tags %}
  <a href="{{ siteUrl('products', { tag: tag }) }}">{{ tag|title }}</a>
  {# -> <a href="https://mydomain.com/products?tag=herbal">Herbal</a> #}
{% endfor %}

{# Images: #}
{% for image in product.images %}
  <img src="{{ image.src }}" alt="{{ image.alt }}">
    {# -> <img src="https://cdn.shopify.com/..." alt="Bubbly Soda"> #}
{% endfor %}

{# Variants: #}
<select name="variantId">
  {% for variant in product.variants %}
    <option value="{{ variant.id }}">{{ variant.title }} ({{ variant.price|currency }})</option>
  {% endfor %}
</select>
```

### Variants and Pricing

Products don’t have a price, despite what the Shopify UI might imply—instead, every product has at least one
[Variant](https://shopify.dev/api/admin-rest/2025-07/resources/product-variant#resource-object).

You can get an array of variant objects for a product by accessing `product.variants` or calling [`product.getVariants()`](#productgetvariants). The product element also provides convenience methods for getting the [default](#productgetdefaultvariant) and [cheapest](#productgetcheapestvariant) variants, but you can filter them however you like with Craft’s [`collect()`](https://craftcms.com/docs/5.x/reference/twig/functions.html#collect) Twig function.

Unlike products, variants in Craft…

- …are represented (mostly) as [the API](https://shopify.dev/api/admin-rest/2025-07/resources/product-variant#resource-object) returns them;
- …the `metafields` property is accessible in addition to the API’s returned properties;
- …use Shopify’s convention of underscores in property names instead of exposing [camel-cased equivalents](#native-attributes);
- …are plain associative arrays;
- …have no methods of their own;

Once you have a reference to a variant, you can output its properties:

```twig
{% set defaultVariant = product.getDefaultVariant() %}

{{ defaultVariant.price|currency }}
```

> [!NOTE]
> The built-in [`currency`](https://craftcms.com/docs/5.x/reference/twig/filters.html#currency) Twig filter is a great way to format money values.

### Using Options

Options are Shopify’s way of distinguishing variants on multiple axes.

If you want to let customers pick from options instead of directly select variants, you will need to resolve which variant a given combination points to.

<details>
<summary>Form</summary>

```twig
<form id="add-to-cart" method="post" action="{{ craft.shopify.store.getUrl('cart/add') }}">
  {# Create a hidden input to send the resolved variant ID to Shopify: #}
  {{ hiddenInput('id', null, {
    id: 'variant',
    data: {
      variants: product.variants,
    },
  }) }}

  {# Create a dropdown for each set of options: #}
  {% for option in product.options %}
    <label>
      {{ option.name }}
      {# The dropdown includes the option’s `position`, which helps match it with the variant, later: #}
      <select data-option="{{ option.position }}">
        {% for val in option.values %}
          <option value="{{ val }}">{{ val }}</option>
        {% endfor %}
      </select>
    </label>
  {% endfor %}

  <button>Add to Cart</button>
</form>
```

</details>

<details>

<summary>Script</summary>

The code below can be added to a [`{% js %}` tag](https://craftcms.com/docs/5.x/reference/twig/tags.html#js), alongside the form code.

```js
// Store references to <form> elements:
const $form = document.getElementById("add-to-cart");
const $variantInput = document.getElementById("variant");
const $optionInputs = document.querySelectorAll("[data-option]");

// Create a helper function to test a map of options against known variants:
const findVariant = (options) => {
  const variants = JSON.parse($variantInput.dataset.variants);

  // Use labels for the inner and outer loop so we can break out early:
  variant: for (const v in variants) {
    option: for (const o in options) {
      // Option values are stored as `option1`, `option2`, or `option3` on each variant:
      if (variants[v][`option${o}`] !== options[o]) {
        // Didn't match one of the options? Bail:
        continue variant;
      }
    }

    // Nice, all options matched this variant! Return it:
    return variants[v];
  }
};

// Listen for change events on the form, rather than the individual option menus:
$form.addEventListener("change", (e) => {
  const selectedOptions = {};

  // Loop over option menus and build an object of selected values:
  $optionInputs.forEach(($input) => {
    // Add the value under the "position" key
    selectedOptions[$input.dataset.option] = $input.value;
  });

  // Use our helper function to resolve a variant:
  const variant = findVariant(selectedOptions);

  if (!variant) {
    console.warn("No variant exists for options:", selectedOptions);

    return;
  }

  // Assign the resolved variant’s ID to the hidden input:
  $variantInput.value = variant.id;
});

// Trigger an initial `change` event to simulate a selection:
$form.dispatchEvent(new Event("change"));
```

</details>

### Cart

Your customers can add products to their cart directly from your Craft site:

```twig
{% set product = craft.shopifyProducts.one() %}

<form action="{{ craft.shopify.store.getUrl('cart/add') }}" method="post">
  <select name="id">
    {% for variant in product.getVariants() %}
      <option value="{{ variant.id }}">{{ variant.title }}</option>
    {% endfor %}
  </select>

  {{ hiddenInput('qty', 1) }}

  <button>Add to Cart</button>
</form>
```

### JS Buy SDK

On-site cart management and checkout are not currently supported in a native way.

However, Shopify provides the (deprecated) [Javascript Buy SDK](https://shopify.dev/custom-storefronts/tools/js-buy) as a means of interacting with their [Storefront API](#storefront-api-client) to create completely custom shopping experiences.

> [!NOTE]
> Use of the Storefront API (directly, or via the Buy SDK or JS Buy Button) requires a different [access key](https://help.shopify.com/en/manual/apps/custom-apps#update-storefront-api-access-scopes-for-a-custom-app), and assumes that you have published your products into the Storefront app’s [sales channel](https://shopify.dev/custom-storefronts/tools/js-buy#step-2-make-your-products-and-collections-available).
>
> Your public Storefront API token can be stored with your other credentials in `.env` and output in your front-end with the `{{ getenv('...') }}` Twig helper—or just baked into a Javascript bundle. **Keep your other secrets safe!** This is the only one that can be disclosed.

The plugin makes no assumptions about how you use your product data in the front-end, but provides the tools necessary to connect it with the SDK. As an example, let’s look at how you might render a list of products in Twig, and hook up a custom client-side cart…

#### Shop Template: `templates/shop.twig`

```twig
{# Include the Buy SDK on this page: #}
{% do view.registerJsFile('https://sdks.shopifycdn.com/js-buy-sdk/v2/latest/index.umd.min.js', {POS_HEAD) %}

{# Register your own script file (see “Custom Script,” below): #}
{% do view.registerJsFile('/assets/js/shop.js') %}

{# Load some products: #}
{% set products = craft.shopifyProducts().all() %}

<ul>
  {% for product in products %}
    {# For now, we’re only handling a single variant: #}
    {% set defaultVariant = product.getVariants()|first %}

    <li>
      {{ product.title }}
      <button
        class="buy-button"
        data-default-variant-id="{{ defaultVariant.id }}">Add to Cart</button>
    </li>
  {% endfor %}
</ul>
```

#### Custom Script: `assets/js/shop.js`

This script must be registered _after_ the Buy SDK.

```js
// Initialize a client:
const client = ShopifyBuy.buildClient({
  domain: "my-storefront.myshopify.com",
  storefrontAccessToken: "...",
});

// Create a simple logger for the cart’s state:
const logCart = (c) => {
  console.log(c.lineItems);
  console.log(`Checkout URL: ${c.webUrl}`);
};

// Create a cart or “checkout” (or perhaps load one from `localStorage`):
client.checkout.create().then((checkout) => {
  const $buyButtons = document.querySelectorAll(".buy-button");

  // Add a listener to each button:
  $buyButtons.forEach(($b) => {
    $b.addEventListener("click", (e) => {
      // Read the variant ID off the product:
      client.checkout
        .addLineItems(checkout.id, [
          {
            // Build the Storefront-style resource identifier:
            variantId: `gid://shopify/ProductVariant/${$b.dataset.defaultVariantId}`,
            quantity: 1,
          },
        ])
        .then(logCart); // <- Log the changes!
    });
  });
});
```

### Buy Button JS

The above example can be simplified with the [Buy Button JS](https://shopify.dev/custom-storefronts/tools/buy-button), which provides some ready-made UI components, like a fully-featured cart. The principles are the same:

1. Make products available via the appropriate sales channels in Shopify;
2. Output synchronized product data in your front-end;
3. Initialize, attach, or trigger SDK functionality in response to events, using Shopify-specific identifiers from step #2;

### Storefront API Client

For fully custom front-end solutions, consider the [Storefront API Javascript client](https://github.com/Shopify/shopify-app-js/tree/main/packages/api-clients/storefront-api-client), which is built and maintained with the new GraphQL API in mind.

```twig
{% do view.registerJsFile('https://unpkg.com/@shopify/storefront-api-client@1.0.5/dist/umd/storefront-api-client.min.js') %}

<script>
  // Note that these values are interpolated into the script tag with Twig!
  const client = ShopifyStorefrontAPIClient.createStorefrontApiClient({
    storeDomain: '{{ craft.shopify.settings.hostName }}',
    apiVersion: '{{ craft.shopify.settings.apiVersion }}',
    publicAccessToken: '{{ getenv('SHOPIFY_PUBLIC_ACCESS_TOKEN') }}',
  });
</script>
```

See the [usage examples](https://github.com/Shopify/shopify-app-js/tree/main/packages/api-clients/storefront-api-client#usage-examples) for ideas. Many queries will require Shopify identifiers, which you can output as hidden attributes:

```twig
{% for variant in product.variants %}
  <button class="buy-button" data-variant-id="{{ variant.id }}">Buy {{ variant.title }}</button>
{% endfor %}
```

You would then consume these GIDs in Javascript, passing them to queries via the Shopify client. Here are the two GraphQL query fragments for creating and updating a cart:

```js
const createCartMutation = `
  mutation cartCreate($input: CartInput) {
    cartCreate(input: $input) {
      cart {
        id
      }
    }
  }
`;

const updateCartMutation = `
  mutation cartLinesAdd($cartId: ID!, $lines: [CartLineInput!]!) {
    cartLinesAdd(cartId: $cartId, lines: $lines) {
      cart {
        # Cart fields
      }
      userErrors {
        field
        message
      }
      warnings {
        # CartWarning fields
      }
    }
  }
`;
```

…and the corresponding plumbing to connect those queries to the DOM elements and `localStorage`:

```js
async function getCartId() {
    // Have we already done this? Use an existing cart ID, if available:
    if (localStorage.getItem('shopifyCartGid')) {
        return localStorage.getItem('shopifyCartGid');
    }

    const { data, errors, extensions } = await client.request(createCartMutation, {
        variables: {
            input: {
                // Accepted parameters are available in the documentation:
                // https://shopify.dev/docs/api/storefront/latest/mutations/cartCreate
            },
        },
    });

    // Ok, save it for later!
    localStorage.setItem('shopifyCartGid', data.cartCreate.cart.id);

    return localStorage.getItem('shopifyCartGid');
}

function addItem(cartId, $el) {
    const line = {
        quantity: 1,
        // The Shopify GID was set on the button as `data-variant-id`:
        merchandiseId: $el.dataset.variantId,
    };

    return client.request(updateCartMutation, {
        variables: {
            cartId,
            lines: [line],
        },
    });
}

// Find "buy buttons" and listen for clicks:
const $buyButtons = document.getElementsByClassName('buy-button');

Array.from($buyButtons).forEach(function($bb) {
    $bb.addEventListener('click', function(e) {
        // Ensure we have a cart ID, then add the clicked item:
        getCartId()
            .then(function(cartId) {
                return addItem(cartId, $bb);
            })
            .then(console.log);
    });
});
```

> [!WARNING]
> This is just a slice of the required functionality for an on-site cart—the actual implementation depends largely on what features you want to offer customers, your front-end stack, and your appetite for dealing directly with the GraphQL client!

### Checkout

While solutions exist for creating a customized shopping experience, _checkout will always happen on Shopify’s platform_. This is a policy matter, not a technical limitation of the plugin (or any other integration, for that matter)—Shopify’s checkout flow is fast, reliable, secure, and familiar to many shoppers.

If you want your customers’ entire journey to be kept on-site, we encourage you to try out our powerful ecommerce plugin, [Commerce](https://craftcms.com/commerce).

### Helpers

In addition to [product element methods](#methods), the plugin exposes its API to Twig via `craft.shopify`.

#### API Service

> [!WARNING]
> Use of API calls in Twig blocks rendering and—depending on traffic—may cause timeouts and/or failures due to [rate limits](#rate-limits). Consider using the [`{% cache %}` tag](https://craftcms.com/docs/5.x/reference/twig/tags.html#cache) with a key and specific expiry time to avoid making a request every time a template is rendered:
>
> ```twig
> {% cache using key "shopify:collections" for 10 minutes %}
>   {# API calls + output... #}
> {% endcache %}
> ```

<details>
<summary>Legacy REST API</summary>

> [!DANGER]
> The Admin REST API has been deprecated. This information is provided only for posterity; the methods still exist in the plugin, but may stop returning data some time in 2025.

Issue requests to the Shopify Admin API via `craft.shopify.api`:

```twig
{% set req = craft.shopify.api.get('custom_collections') %}
{% set collections = req.response.custom_collections %}
```

The schema for each API resource will differ. Consult the [Shopify API documentation](https://shopify.dev/api/admin-rest) for more information.

</details>

You can make arbitrary GraphQL queries against the Shopify API with `craft.shopify.api.query()`:

```twig
{% set gql %}
  {
    collections(first: 10) {
      nodes {
        id
        title
      }
    }
  }
{% endset %}

{% set response = craft.shopify.api.query(gql) %}
{% set collections = response.nodes ?? [] %}

{% if collections is not empty %}
  <ul>
    {% for collection in collections %}
      <li>{{ collection.title }}</li>
    {% endfor %}
  </ul>
{% endif %}
```

The Shopify GraphQL client is also available if you need to safely pass variables (like pagination offsets or search strings), or make mutations:

```twig
{% set response = craft.shopify.api.gqlClient.query({
  query: gql,
  variables: {
    num: 10,
  },
}) %}

{# The plugin does not intercept response data, so you must unpack it based on what was requested: #} 
{% set data = response.data.nodes %}
```

#### Store Service

A simple URL generator is available via `craft.shopify.store`. You may have noticed it in the [cart](#cart) example, above—but it is a little more versatile than that!

```twig
{# Create a link to add a product/variant to the cart: #}
{{ tag('a', {
  href: craft.shopify.store.getUrl('cart/add', {
    id: variant.id,
    quantity: 1,
  }),
  text: 'Add to Cart',
  target: '_blank',
}) }}
```

The same params argument can be passed to a product element’s `getShopifyUrl()` method:

```twig
{% for variant in product.getVariants() %}
  <a href="{{ product.getShopifyUrl({ id: variant.id }) }}">{{ variant.title }}</a>
{% endfor %}
```

## Product Field

The plugin provides a _Shopify Products_ field, which uses the familiar [relational field](https://craftcms.com/docs/5.x/system/relations.html) UI to allow authors to select synchronized Product elements.

Relationships defined with the _Shopify Products_ field use stable element IDs under the hood. When Shopify products are archived or deleted, the corresponding elements will also be updated in Craft, and naturally filtered out of your query results—including those explicitly attached via a _Shopify Products_ field.

These fields return a [product query](#querying-products), which you can customize using any [supported query param](#query-parameters)—or immediately execute:

```twig
{% set featuredProducts = category.myProductsField.all() %}

<ul>
  {% for product in featuredProducts %}
    <li>{{ product.link }}</li>
  {% endfor %}
</ul>
```

---

## Going Further

### Settings

The following settings can also be set via a `shopify.php` file in your `config/` directory.

| Setting                      | Type   | Default | Description                                                                                                                                                                                                   |
|------------------------------|--------|---------|---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------|
| `apiKey`                     | `string` | — | Shopify API key.                                                                                                                                                                                              |
| `apiSecretKey`               | `string` | — | Shopify API secret key.                                                                                                                                                                                       |
| `apiVersion`                 | `string` | — | Shopify [API version](https://shopify.dev/docs/api/usage/versioning) description.                                                                                                                             |
| `accessToken`                | `string` | — | Shopify API access token.                                                                                                                                                                                     |
| `contextualPricingCountries` | `string` | — | Comma-separated list of [two-letter country codes](https://shopify.dev/docs/api/admin-graphql/latest/enums/CountryCode) that determine which [contextual prices](#contextual-pricing) are loaded via the API. |
| `hostName`                   | `string` | — | Shopify [host name](#store-hostname).                                                                                                                                                                         |
| `uriFormat`                  | `string` | — | Product element URI format.                                                                                                                                                                                   |
| `template`                   | `string` | — | Product element template path.                                                                                                                                                                                |

> [!NOTE]
> Setting `apiKey`, `apiSecretKey`, `apiVersion`, `accessToken`, or `hostName` via `shopify.php` will override Project Config values set via the control panel during [app setup](#create-a-shopify-app). You can still reference environment values from the config file with `craft\helpers\App::env()`.

### Events

Learn about [responding to events](https://craftcms.com/docs/5.x/extend/events.html) in the Craft extension documentation.

#### `craft\shopify\services\Products::EVENT_BEFORE_SYNCHRONIZE_PRODUCT`

Emitted just prior to a product element is saved with new Shopify data. The `craft\shopify\events\ShopifyProductSyncEvent` extends `craft\events\CancelableEvent`, so setting `$event->isValid` allows you to prevent the new data from being saved.

The event object has three properties:

- `element`: The product element being updated.
- `source`: The Shopify product object that was applied.

```php
use craft\shopify\events\ShopifyProductSyncEvent;
use craft\shopify\services\Products;
use yii\base\Event;

Event::on(
  Products::class,
  Products::EVENT_BEFORE_SYNCHRONIZE_PRODUCT,
  function(ShopifyProductSyncEvent $event) {
    // Example 1: Cancel the sync if a flag is set via a Shopify metafield:
    $metafields = $event->element->getMetafields();

    if ($metafields['do_not_sync'] ?? false) {
      $event->isValid = false;
    }

    // Example 2: Set a custom field value from metafield data:
    $event->element->setFieldValue('myNumberFieldHandle', $metafields['cool_factor']);
  }
);
```

> [!WARNING]
> Do not manually save changes made in this event handler. The plugin will take care of this for you!

### Element API

Your synchronized products can be published into an [Element API](https://plugins.craftcms.com/element-api) endpoint, just like any other element type. This allows you to set up a local JSON feed of products, decorated with any content you’ve added in Craft:

```php
use craft\shopify\elements\Product;

return [
  'endpoints' => [
    'products.json' => function() {
      return [
        'elementType' => Product::class,
        'criteria' => [
          'publishedScope' => 'web',
          'with' => [
            ['myImageField']
          ],
        ],
        'transformer' => function(Product $product) {
          $image = $product->myImageField->one();

          return [
            'title' => $product->title,
            'variants' => $product->getVariants(),
            'image' => $image ? $image->getUrl() : null,
          ];
        },
      ];
    },
  ],
];
```

### Real-Time Pricing Queries

Shopify does not emit webhooks for changes in [price catalogs for different markets](https://shopify.dev/docs/apps/build/markets/catalogs-different-markets#considerations). If you use these features, pricing stored in locally-synchronized product elements may become stale.

To fetch current prices directly from Shopify's API in your Twig templates, use the GraphQL client with a query that includes [contextual pricing](https://shopify.dev/docs/api/admin-graphql/latest/objects/ProductVariantContextualPricing):

```twig
{# Define the GraphQL query with contextual pricing for a specific country #}
{% set priceQuery %}
query getProductPrice($id: ID!) {
  product(id: $id) {
    variants(first: 100) {
      nodes {
        id
        title
        price
        compareAtPrice
        gbPricing: contextualPricing(context: {country: GB}) {
          price {
            amount
            currencyCode
          }
          compareAtPrice {
            amount
            currencyCode
          }
        }
      }
    }
  }
}
{% endset %}

{# Execute the query #}
{% set response = craft.shopify.api.query(priceQuery, {
  id: product.shopifyId
}) %}

{# Access the pricing data #}
{% if response %}
  {% for variant in response.variants.nodes %}
    {% set pricing = variant.gbPricing %}
    {% if pricing and pricing.price %}
      {{ pricing.price.amount|currency(pricing.price.currencyCode) }}
    {% else %}
      {{ variant.price|currency }}
    {% endif %}
  {% endfor %}
{% endif %}
```

Key elements of this approach:

- `contextualPricing(context: {country: XX})` returns market-specific prices (use the country code directly, e.g., `GB`, `US`, `DE`)
- Use an alias like `gbPricing:` to name the result for easy access in Twig
- Pass the product's `shopifyId` (already a GID) directly to the query
- The `query()` method returns the first result directly, so access `response.variants` (not `response.data.product.variants`)
- Falls back to the default `variant.price` if contextual pricing is not available

> [!WARNING]
> Real-time API calls add latency to page rendering and count against [rate limits](#rate-limits). If some staleness is acceptable, wrap the query in a [`{% cache %}` tag](https://craftcms.com/docs/5.x/reference/twig/tags.html#cache):
>
> ```twig
> {% cache using key "pricing:#{product.id}:GB" for 5 minutes %}
>   {# pricing query here #}
> {% endcache %}
> ```
