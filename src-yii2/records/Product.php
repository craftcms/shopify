<?php

namespace craft\shopify\records;

/** @deprecated in 9.0.0. Use {@see \CraftCms\Shopify\Product\Models\Product} instead. */
class_alias(\CraftCms\Shopify\Product\Models\Product::class, 'craft\shopify\records\Product');

/** @phpstan-ignore-next-line Gives IDEs and static analysis a real symbol. */
if (false) {
    class Product extends \CraftCms\Shopify\Product\Models\Product
    {
    }
}
