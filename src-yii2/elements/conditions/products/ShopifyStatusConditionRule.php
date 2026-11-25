<?php

namespace craft\shopify\elements\conditions\products;

/** @deprecated in 9.0.0. Use {@see \CraftCms\Shopify\Product\Conditions\ShopifyStatusConditionRule} instead. */
class_alias(\CraftCms\Shopify\Product\Conditions\ShopifyStatusConditionRule::class, 'craft\shopify\elements\conditions\products\ShopifyStatusConditionRule');

/** @phpstan-ignore-next-line Gives IDEs and static analysis a real symbol. */
if (false) {
    class ShopifyStatusConditionRule extends \CraftCms\Shopify\Product\Conditions\ShopifyStatusConditionRule
    {
    }
}
