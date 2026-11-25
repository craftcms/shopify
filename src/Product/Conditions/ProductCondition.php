<?php

declare(strict_types=1);

namespace CraftCms\Shopify\Product\Conditions;

use CraftCms\Cms\Element\Conditions\ElementCondition;
use Override;

/**
 * Product query condition.
 */
class ProductCondition extends ElementCondition
{
    #[Override]
    protected function selectableConditionRules(): array
    {
        return array_merge(parent::selectableConditionRules(), [
            ProductTypeConditionRule::class,
            ShopifyStatusConditionRule::class,
            VendorConditionRule::class,
            HandleConditionRule::class,
            TagsConditionRule::class,
            TemplateSuffixConditionRule::class,
        ]);
    }
}
