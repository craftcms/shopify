<?php

namespace craft\shopify\elements\conditions\products;

/** @deprecated in 9.0.0. Use {@see \CraftCms\Shopify\Product\Conditions\TagsConditionRule} instead. */
class_alias(\CraftCms\Shopify\Product\Conditions\TagsConditionRule::class, 'craft\shopify\elements\conditions\products\TagsConditionRule');

/** @phpstan-ignore-next-line Gives IDEs and static analysis a real symbol. */
if (false) {
    class TagsConditionRule extends \CraftCms\Shopify\Product\Conditions\TagsConditionRule
    {
    }
}
