<?php

declare(strict_types=1);

namespace CraftCms\Shopify\Product\Conditions;

use CraftCms\Cms\Condition\BaseTextConditionRule;
use CraftCms\Cms\Element\Conditions\Contracts\ElementConditionRuleInterface;
use CraftCms\Cms\Element\Conditions\Contracts\ElementQueryConditionRuleInterface;
use CraftCms\Cms\Element\Contracts\ElementInterface;
use CraftCms\Cms\Element\Queries\Contracts\ElementQueryInterface;
use CraftCms\Shopify\Product\Elements\Product;
use CraftCms\Shopify\Product\Queries\ProductQuery;
use Illuminate\Database\Query\Builder;

use function CraftCms\Cms\t;

/**
 * Vendor condition rule.
 */
class VendorConditionRule extends BaseTextConditionRule implements ElementConditionRuleInterface, ElementQueryConditionRuleInterface
{
    public function getLabel(): string
    {
        return t('Vendor', category: 'shopify');
    }

    public function modifyQuery(Builder $query, ElementQueryInterface $elementQuery): void
    {
        ProductQuery::applyVendor($query, $this->paramValue());
    }

    public function matchElement(ElementInterface $element): bool
    {
        /** @var Product $element */
        return $this->matchValue($element->vendor);
    }
}
