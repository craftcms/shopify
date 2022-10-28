<p align="center"><img src="./src/icon.svg" width="100" height="100" alt="Shopify icon"></p>

<h1 align="center">Shopify for Craft CMS</h1>

## About

The Shopify plugin for Craft CMS allows you to connect your Craft CMS site to your Shopify store.

The plugin syncs all your Shopify stores products to Craft, and keeps them updated.

## Requirements

This plugin requires Craft CMS 4.0.0 or later.

## Installation

To install the plugin, follow these instructions.

1.  Open your terminal and go to your Craft project:

        cd /path/to/project

2.  Then tell Composer to load the plugin:

        composer require craftcms/shopify

3.  In the Control Panel, go to Settings → Plugins and click the “Install” button for Shopify.

### Setup API Keys and connect your Craft Shopify plugin to the store

1. Log into your Shopify store.
2. Click 'Apps' in the sidebar.
3. Click the 'Develop apps for your store' button under "Build custom apps for your unique needs".
4. Click "Create an app"
5. Name the app something like 'Craft CMS Shopify Plugin'
6. Click 'Creat app'
7. Click the 'Configuration' tab
6. Click "Configure" in the 'Admin API integration' box.

Select the following scopes:
`read_products`
`read_product_listings`
`write_product_listings`
`read_product_listings`

Select the webhooks events version:
`2022-10 (Latest)`

7. Click 'Save' on Admin API integration you just configured.
8. Click the `API credentials` tab.
9. Click install app to add the custom app you just created to your store
10. Click `Install app`
11. Copy the Access Token (you only have one chance to do this) to your settings (or save to your .env file so your settings can reference it).
12. Copy the API Key to your settings (or to your save .env file so your settings can reference it).
12. Copy the Secret Key to your settings (or to your save .env file so your settings can reference it).

13. Enter the above Access Token, API Key, and Secret Key into the plugin settings.
14. Enter the Shopify store URL into the plugin settings in the format: `xxxxx.myshopify.com` (No `http://` or `https://`)
15. Save the shopify plugin settings. The webhooks nav option will now be available.
16. Click on Shopify 'webhooks' in the CP sidebar.
17. Click generate to create the webhooks for the current environment. 

Webhooks
While in development we recommend using the [ngrok](https://ngrok.com/) tool to create a tunnel to your local development environment. This will allow Shopify to send webhooks to your local environment.
Once you are in production you will need to generate the webhooks for that environment and can delete your ngrok if you want.

Product Element

The Product element type represents a product in your Shopify store.
All products are created, updated, and deleted via the Shopify control panel, and are reflected in Craft.

## Syncing Products

Products will be created, updated, and deleted automatically via the webhooks.

If you want to sync all Products you can do so by running the following console command:

    php craft shopify/sync/products

## Fields

In addition to the standard element fields like `id`, `title` and `status` the shopify product element will contain
the following fields which maps to the [Shopify Produce resource](https://shopify.dev/api/admin-rest/2022-10/resources/product#resource-object)

- `shopifyId` The shopifyId field is the unique identifier for the product in your Shopify store. This is a string of integers.
- `shopifyStatus` The Shopify Status field is the status of the product in your Shopify store. Values can be `active`, `draft` or `archived`.
- `handle` The handle field is the unique identifier for the product in your Shopify store. This is a string of characters.
- `productType` The product type field is the product type of the product in your Shopify store. This is a string of characters.
- `bodyHtml` The body html field is the description of the product in your Shopify store. This is a string of HTML. Use the `|raw` filter to output it, if trusted.
- `publishedScope` The published scope field is the published scope of the product in your Shopify store. This is a string of characters, e.g 'web'.
- `tags` The tags field is the tags of the product in your Shopify store. This is a an array of tags strings.
- `templateSuffix` The suffix of the Liquid template used for the product page in Shopify. String of characters.
- `vendor` The vendor field is the vendor of the product in your Shopify store. This is a string of characters.
- `images` The images field is the images of the product in your Shopify store. This is an array of image objects. https://shopify.dev/api/admin-rest/2022-10/resources/product-image#resource-object
- `options` The options field is the options of the product in your Shopify store. This is an array of option objects.
- `createdAt` The created at field is the date the product was created in your Shopify store. This is a DateTime object.
- `publishedAt` The published at field is the date the product was published in your Shopify store. This is a DateTime object.
- `updatedAt` The updated at field is the date the product was updated in your Shopify store. This is a DateTime object.

## Product Status

Products have a `shopifyStatus` property that contains either 'active', 'draft', or 'archived'. This can only be updated from the Shopify CP.

Products have a status of either 'live', 'shopifyDraft', 'shopifyArchived', or 'disabled'.

- `live` - The product is `active` in your Shopify store and `enabled` in the Product edit page.
- `shopifyDraft` - The product is `draft` in your Shopify store and `enabled` in the Product edit page.
- `shopifyArchived` - The product is `archived` in your Shopify store and `enabled` in the Product edit page.
- `disabled` - The product is *any* status in your Shopify store but `disabled` on the Product edit page.

## Product Element Queries

The Product element can be queried like any other entry in the system:

For example:

`craft.shopifyProducts.limit(10).all()`

`craft.shopifyProducts.status('shopifyDraft').all()`

# Migrating from version 2 of the plugin

You can remove the old plugin from your composer.json but do not uninstall it.

If you used the old product field, after upgrading you will see a 'missing field' in your field layouts.
To migrate to a new field:

1. Add the new 'Shopify Product' field to your field layout with a different field name. 
2. Run the below command to resave the data from the old field to the new field.

Note: Replace the section handle and field names with your own below

`blog` should be entry section you used.
`oldShopifyField` is the field handle from the previous version of the plugin
`shopifyProductsRelatedField` is the new field handle for the standard product relation field 
```
php craft resave/entries --section=blog --set shopifyProductsRelatedField --to "fn(\$entry) => collect(json_decode(\$entry->oldShopifyField))->map(fn (\$item) => \craft\shopify\Plugin::getInstance()->getProducts()->getProductIdByShopifyId(\$item))->unique()->all()"
```

After making the data migration, you can access the new field in your templates like this:

```
{% set products = entry.shopifyProductsRelatedField.all() %}
{% for product in products %}
    {{ product.handle }}
{% endfor %}
```

There is no longer the need to make an API call to Shopify to get the product data. The data is now stored in the Craft product element.