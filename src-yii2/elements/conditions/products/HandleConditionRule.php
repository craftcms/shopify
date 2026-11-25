<?php

namespace craft\shopify\elements\conditions\products;

/** @deprecated in 9.0.0. Use {@see \CraftCms\Shopify\Product\Conditions\HandleConditionRule} instead. */
class_alias(\CraftCms\Shopify\Product\Conditions\HandleConditionRule::class, 'craft\shopify\elements\conditions\products\HandleConditionRule');

/** @phpstan-ignore-next-line Gives IDEs and static analysis a real symbol. */
if (false) {
    class HandleConditionRule extends \CraftCms\Shopify\Product\Conditions\HandleConditionRule
    {
    }
}
