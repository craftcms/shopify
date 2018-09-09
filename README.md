# Shopify plugin for Craft CMS 3.x


![Screenshot](src/img/plugin-logo.jpg)

## Requirements

This plugin requires Craft CMS 3.0.0-beta.23 or later.

## Installation

#### VIA Composer
Install the plugin via composer into your craft 3 project:

    composer require nmaier95/shopify-product-fetcher

In the Control Panel of Craft, go to Settings → Plugins and click the “Install” button for shopify.

#### Craft Plugin Store

We are listed inside the official Craft Plugin Store. You may directly install it here as well.

#### Manually
Please see the official docs of Craft:

https://docs.craftcms.com/v3/extend/plugin-guide.html#loading-your-plugin-into-a-craft-project


## Configuring shopify

Once the plugin is installed successfully, a new icon with this' plugins logo on the settings-page appeared. Clicking it takes you to the settings of the plugin. 

![Screenshot](src/img/settings.png)

Please stick to the format of the "Hostname" field like in the example above.

## Using shopify

There will be an additional field of type "Shopify Product" in the list of all available field-types when creating a new field for a group. 
Then add this field/group to your section layout and you are ready to go. 
When editing the section it´ll now automatically fetch products from your store into the field to select them from. 
In the background only the product id gets saved into your database. 
Via the saved product-id you are then able to fetch specific products inside of your templates.

```twig
{% set shopify = craft.shopify.getProductById({ id: entry.productId, fields: 'variants' }) %}
{# Or all products: #}
{% set shopify = craft.shopify.getProducts({ fields: 'variants' }) %}

<form action="//{{craft.shopify.getSettings().hostname}}/cart/add" method="post">
    <select name="id">
        {% for variant in shopify.variants %}
            <option value="{{ variant.id }}">{{ variant.title }} - ${{ variant.price }}</option>
        {% endfor %}
    </select>
    <input type="hidden" name="return_to" value="back">
    <button type="submit">Add to Cart</button>
</form>
```
The variants parameter is documented inside of official shopify-api-docs.

Brought to you by [niklas maier](https://maier-niklas.de/)
