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

1. Log into your store.
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

5. Click Save on Admin API integration you just configured.
6. Click on `API credentials` tab
7. Click install app to add the custom app you just created to your store
8. Click `Install app`
9. Copy the access token (you only have one chance to do this) to your settings (or to your .env file so your settings can reference it).
