<?php

namespace craft\shopify\elements\conditions\products;

/** @deprecated in 9.0.0. Use {@see \CraftCms\Shopify\Product\Conditions\ProductCondition} instead. */
class_alias(\CraftCms\Shopify\Product\Conditions\ProductCondition::class, 'craft\shopify\elements\conditions\products\ProductCondition');

/** @phpstan-ignore-next-line Gives IDEs and static analysis a real symbol. */
if (false) {
    class ProductCondition extends \CraftCms\Shopify\Product\Conditions\ProductCondition
    {
    }
}
