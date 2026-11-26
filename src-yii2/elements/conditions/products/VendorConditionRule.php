<?php

namespace craft\shopify\elements\conditions\products;

/** @deprecated in 9.0.0. Use {@see \CraftCms\Shopify\Product\Conditions\VendorConditionRule} instead. */
class_alias(\CraftCms\Shopify\Product\Conditions\VendorConditionRule::class, 'craft\shopify\elements\conditions\products\VendorConditionRule');

/** @phpstan-ignore-next-line Gives IDEs and static analysis a real symbol. */
if (false) {
    class VendorConditionRule extends \CraftCms\Shopify\Product\Conditions\VendorConditionRule
    {
    }
}
