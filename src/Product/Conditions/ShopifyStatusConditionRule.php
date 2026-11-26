<?php

declare(strict_types=1);

namespace CraftCms\Shopify\Product\Conditions;

use CraftCms\Cms\Condition\BaseMultiSelectConditionRule;
use CraftCms\Cms\Element\Conditions\Contracts\ElementConditionRuleInterface;
use CraftCms\Cms\Element\Conditions\Contracts\ElementQueryConditionRuleInterface;
use CraftCms\Cms\Element\Contracts\ElementInterface;
use CraftCms\Cms\Element\Queries\Contracts\ElementQueryInterface;
use CraftCms\Cms\Support\Str;
use CraftCms\Shopify\Product\Elements\Product;
use CraftCms\Shopify\Product\Queries\ProductQuery;
use Illuminate\Database\Query\Builder;

use function CraftCms\Cms\t;

/**
 * Shopify status condition rule.
 */
class ShopifyStatusConditionRule extends BaseMultiSelectConditionRule implements ElementConditionRuleInterface, ElementQueryConditionRuleInterface
{
    public function getLabel(): string
    {
        return t('Shopify Status', category: 'shopify');
    }

    protected function options(): array
    {
        return array_map(fn(string $status) => ['value' => $status, 'label' => Str::title($status)], [
            Product::SHOPIFY_STATUS_ACTIVE,
            Product::SHOPIFY_STATUS_DRAFT,
            Product::SHOPIFY_STATUS_ARCHIVED,
        ]);
    }

    public function modifyQuery(Builder $query, ElementQueryInterface $elementQuery): void
    {
        ProductQuery::applyShopifyStatus($query, $this->paramValue());
    }

    public function matchElement(ElementInterface $element): bool
    {
        /** @var Product $element */
        return $this->matchValue($element->shopifyStatus);
    }
}
