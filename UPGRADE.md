## Upgrading from 5.x to 6.x

All json based attributes of the product including:

- product.options
- product.variants
- product.images
- product.metafields

Will have a different structure. For example an option `product_id` is now removed from the options data.

Product element changes:

product.publishedScope removed
product.tags now returns an array of tags instead of a string of comma seperated tags

TODO: add before and after json objects

1. upgrade to 6.x (change composer version to 6.0.0)
2. run migrations 'php craft up'
3. re-register webhooks "cp > shopify > webhooks > 'create'"
4. run a full sync again "cp > utilities > Shopify Sync > 'sync all'"
5. Refresh page to monitor progress of sync. 
