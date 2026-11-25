<?php

namespace craft\shopify\records;

/** @deprecated in 9.0.0. Use {@see \CraftCms\Shopify\Api\Models\ShopifyData} instead. */
class_alias(\CraftCms\Shopify\Api\Models\ShopifyData::class, 'craft\shopify\records\ShopifyData');

/** @phpstan-ignore-next-line Gives IDEs and static analysis a real symbol. */
if (false) {
    class ShopifyData extends \CraftCms\Shopify\Api\Models\ShopifyData
    {
    }
}
