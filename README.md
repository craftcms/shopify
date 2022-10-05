<p align="center"><img src="./src/icon.svg" width="100" height="100" alt="Shopify icon"></p>

<h1 align="center">Shopify for Craft CMS</h1>

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
6. Click "Configure Admin API" integration

Scopes needed:
Products
`read_products`
`read_product_listings`

Webhook events version:
`2022-04`

7. Click Save on Admin API integration you just configured.
8. Click on `API credentials` tab
9. Click install app to add the custom app you just created to your store
10. Click `Install app`
11. Copy the access token (you only have one chance to do this) to your settings (or to your .env file so your settings can reference it).

Product Element

The Product element type represents a product in your Shopify store.
All products are created, updated, and deleted via the Shopify control panel.

## Fields

In addition to the standard element field like `id`, `title` and `status` the shopify product element will contain
the following fields which maps to the [Shopify Product resouce](https://shopify.dev/api/admin-rest/2022-10/resources/product#resource-object)

- `shopifyId` The shopifyId field is the unique identifier for the product in your Shopify store. This is a string of integers.
- `shopifyStatus` The Shopify Status field is the status of the product in your Shopify store. Values can be `active`, `draft` or `archived`.
- `handle` The handle field is the unique identifier for the product in your Shopify store. This is a string of characters.
- `productType` The product type field is the product type of the product in your Shopify store. This is a string of characters.
- `bodyHtml` The body html field is the description of the product in your Shopify store. This is a string of HTML. Use the `|raw` filter to output it if trusted.
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

Shopify Products have a status of either 'active', 'draft', or 'archived'. This can only be updated from the Shopify CP.

Product elements have a status of either 'live', 'pending', or 'disabled'.

- `live` - The product is `active` in your Shopify store and enabled in the Product edit page.
- `pending` - The product is `draft` or `archived` in your Shopify store and enabled in the Product edit page.
- `disabled` - The product is any status in your Shopify store but disabled on the Product edit page.

## Product Element Queries

The Product element can be queried like any other entry in the system:

For example:

`craft.shopifyProducts.limit(10).all()`

`craft.shopifyProducts.status('pending').all()`

