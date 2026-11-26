<?php

namespace craft\shopify\elements;

/** @deprecated in 9.0.0. Use {@see \CraftCms\Shopify\Product\Elements\Product} instead. */
class_alias(\CraftCms\Shopify\Product\Elements\Product::class, 'craft\shopify\elements\Product');

/** @phpstan-ignore-next-line Gives IDEs and static analysis a real symbol. */
if (false) {
    class Product extends \CraftCms\Shopify\Product\Elements\Product
    {
    }
}
