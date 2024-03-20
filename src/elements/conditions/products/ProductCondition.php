<?php

namespace craft\shopify\elements\conditions\products;

use craft\elements\conditions\ElementCondition;

/**
 * Product query condition.
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 3.0.0
 */
class ProductCondition extends ElementCondition
{
    /**
     * @inheritdoc
     */
    protected function selectableConditionRules(): array
    {
        return array_merge(parent::selectableConditionRules(), [
            ProductTypeConditionRule::class,
            ShopifyStatusConditionRule::class,
            VendorConditionRule::class,
            HandleConditionRule::class,
            TagsConditionRule::class,
        ]);
    }
}
