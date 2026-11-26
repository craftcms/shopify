<?php

namespace craft\shopify\elements\conditions\products;

/** @deprecated in 9.0.0. Use {@see \CraftCms\Shopify\Product\Conditions\TemplateSuffixConditionRule} instead. */
class_alias(\CraftCms\Shopify\Product\Conditions\TemplateSuffixConditionRule::class, 'craft\shopify\elements\conditions\products\TemplateSuffixConditionRule');

/** @phpstan-ignore-next-line Gives IDEs and static analysis a real symbol. */
if (false) {
    class TemplateSuffixConditionRule extends \CraftCms\Shopify\Product\Conditions\TemplateSuffixConditionRule
    {
    }
}
