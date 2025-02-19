## Upgrading from 5.x to 6.x

All json based attributes of the product including:

**product.options**
before
```json
[
  {
    "id": 8545236451379,
    "product_id": 6656149192755,
    "name": "Size",
    "position": 1,
    "values": [
      "XX Small",
      "X Small",
      "Small",
      "Medium",
      "Large",
      "X Large",
      "XX Large"
    ]
  },
  ...
]
```

after
```json
[
  {
    "id": "gid:\/\/shopify\/ProductOption\/8545236451379",
    "name": "Size",
    "values": [
      "XX Small",
      "X Small",
      "Small",
      "Medium",
      "Large",
      "X Large",
      "XX Large"
    ],
    "position": 1,
    "optionValues": [
      {
        "id": "gid:\/\/shopify\/ProductOptionValue\/79214084147",
        "name": "XX Small",
        "hasVariants": true
      },
      {
        "id": "gid:\/\/shopify\/ProductOptionValue\/79214116915",
        "name": "X Small",
        "hasVariants": true
      },
      {
        "id": "gid:\/\/shopify\/ProductOptionValue\/79214149683",
        "name": "Small",
        "hasVariants": true
      },
      {
        "id": "gid:\/\/shopify\/ProductOptionValue\/79214182451",
        "name": "Medium",
        "hasVariants": true
      },
      {
        "id": "gid:\/\/shopify\/ProductOptionValue\/79214215219",
        "name": "Large",
        "hasVariants": true
      },
      {
        "id": "gid:\/\/shopify\/ProductOptionValue\/79214247987",
        "name": "X Large",
        "hasVariants": true
      },
      {
        "id": "gid:\/\/shopify\/ProductOptionValue\/79214280755",
        "name": "XX Large",
        "hasVariants": true
      }
    ]
  },
  ...
]
```

**product.variants**
Contextual pricing https://shopify.dev/docs/api/admin-graphql/2025-01/enums/CountryCode
before
```json
[
  {
    "barcode": "",
    "compare_at_price": "21.00",
    "created_at": "2022-01-05T14:56:08+00:00",
    "fulfillment_service": "manual",
    "grams": 0,
    "id": 39895248437299,
    "image_id": null,
    "inventory_item_id": 41993671016499,
    "inventory_management": "shopify",
    "inventory_policy": "deny",
    "inventory_quantity": 6,
    "old_inventory_quantity": 6,
    "position": 1,
    "presentment_prices": [
      {
        "price": {
          "amount": "15.00",
          "currency_code": "EUR"
        },
        "compare_at_price": {
          "amount": "21.00",
          "currency_code": "EUR"
        }
      }
    ],
    "price": "15.00",
    "product_id": 6656149192755,
    "requires_shipping": true,
    "sku": "",
    "taxable": true,
    "title": "XX Small \/ Black \/ Long Sleeve",
    "updated_at": "2024-03-28T15:26:13+00:00",
    "weight": 0,
    "weight_unit": "lb",
    "option1": "XX Small",
    "option2": "Black",
    "option3": "Long Sleeve",
    "admin_graphql_api_id": "gid:\/\/shopify\/ProductVariant\/39895248437299",
    "metafields": {
      "colour": "Black"
    }
  },
  ...
]
```

after
```json
[
  {
    "id": "gid:\/\/shopify\/ProductVariant\/39895248437299",
    "sku": "",
    "price": "15.00",
    "title": "XX Small \/ Black \/ Long Sleeve",
    "barcode": "",
    "product": {
      "id": "gid:\/\/shopify\/Product\/6656149192755"
    },
    "taxable": true,
    "createdAt": "2022-01-05T14:56:08Z",
    "updatedAt": "2024-03-28T15:26:13Z",
    "__parentId": "gid:\/\/shopify\/Product\/6656149192755",
    "displayName": "Short Sleeve T-shirt - XX Small \/ Black \/ Long Sleeve",
    "inventoryItem": {
      "id": "gid:\/\/shopify\/InventoryItem\/41993671016499",
      "sku": "",
      "tracked": true,
      "unitCost": null,
      "createdAt": "2022-01-05T14:56:10Z",
      "updatedAt": "2022-01-05T14:56:10Z",
      "countryCodeOfOrigin": null
    },
    "compareAtPrice": "21.00",
    "inventoryPolicy": "DENY",
    "inventoryQuantity": 6
  },
  ...
]
```
**product.images**

before
```json
[
  {
    "id": 30764054380595,
    "alt": null,
    "position": 1,
    "product_id": 7136056049715,
    "created_at": "2024-04-08T13:44:14+01:00",
    "updated_at": "2024-04-08T13:44:14+01:00",
    "admin_graphql_api_id": "gid:\\/\\/shopify\\/ProductImage\\/30764054380595",
    "width": 635,
    "height": 560,
    "src": "https:\\/\\/cdn.shopify.com\\/s\\/files\\/1\\/0555\\/1672\\/5299\\/files\\/d841f71ea6845bf6005453e15a18c632.jpg?v=1712580254",
    "variant_ids": []
  },
  ...
]
```

after
```json
[
  {
    "id": "gid:\/\/shopify\/MediaImage\/23117921058867",
    "alt": "",
    "image": {
      "url": "https:\/\/cdn.shopify.com\/s\/files\/1\/0555\/1672\/5299\/files\/d841f71ea6845bf6005453e15a18c632.jpg?v=1712580254",
      "width": 635,
      "height": 560,
      "altText": ""
    },
    "createdAt": "2024-04-08T12:44:14Z",
    "updatedAt": "2024-04-08T12:44:14Z",
    "__parentId": "gid:\/\/shopify\/Product\/7136056049715",
    "mediaContentType": "IMAGE"
  },
  ...
]
```

**product.metafields**
Unchanged

**product.tags**

before
```text
"egnition-sample-data, men, summer, vans"
```

after
```json
[
  "egnition-sample-data", 
  "men", 
  "summer", 
  "vans"
]
```

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
