<?php

namespace craft\shopify\elements\conditions\products;

/** @deprecated in 9.0.0. Use {@see \CraftCms\Shopify\Product\Conditions\ProductTypeConditionRule} instead. */
class_alias(\CraftCms\Shopify\Product\Conditions\ProductTypeConditionRule::class, 'craft\shopify\elements\conditions\products\ProductTypeConditionRule');

/** @phpstan-ignore-next-line Gives IDEs and static analysis a real symbol. */
if (false) {
    class ProductTypeConditionRule extends \CraftCms\Shopify\Product\Conditions\ProductTypeConditionRule
    {
    }
}
