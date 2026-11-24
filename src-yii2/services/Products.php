<?php

namespace craft\shopify\services;

/** @deprecated in 9.0.0. Use {@see \CraftCms\Shopify\Product\Products} instead. */
class_alias(\CraftCms\Shopify\Product\Products::class, 'craft\shopify\services\Products');

/** @phpstan-ignore-next-line Gives IDEs and static analysis a real symbol. */
if (false) {
    class Products extends \CraftCms\Shopify\Product\Products
    {
    }
}
