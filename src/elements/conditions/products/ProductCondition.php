<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

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
     * @TODO remove this method when support for Craft 4 is dropped
     */
    protected function conditionRuleTypes(): array
    {
        /** @phpstan-ignore-next-line */
        return array_merge(parent::conditionRuleTypes(), [
            ProductTypeConditionRule::class,
            ShopifyStatusConditionRule::class,
            VendorConditionRule::class,
            HandleConditionRule::class,
            TagsConditionRule::class,
        ]);
    }

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
            TemplateSuffixConditionRule::class,
        ]);
    }
}
