<?php

declare(strict_types=1);

namespace CraftCms\Shopify\Product\Conditions;

use CraftCms\Cms\Condition\BaseMultiSelectConditionRule;
use CraftCms\Cms\Element\Conditions\Contracts\ElementConditionRuleInterface;
use CraftCms\Cms\Element\Conditions\Contracts\ElementQueryConditionRuleInterface;
use CraftCms\Cms\Element\Contracts\ElementInterface;
use CraftCms\Cms\Element\Queries\Contracts\ElementQueryInterface;
use CraftCms\Cms\Support\Str;
use CraftCms\Shopify\Api\Models\ShopifyData;
use CraftCms\Shopify\Product\Elements\Product;
use CraftCms\Shopify\Product\Queries\ProductQuery;
use Illuminate\Database\Query\Builder;

use function CraftCms\Cms\t;

/**
 * Product type condition rule.
 */
class ProductTypeConditionRule extends BaseMultiSelectConditionRule implements ElementConditionRuleInterface, ElementQueryConditionRuleInterface
{
    public function getLabel(): string
    {
        return t('Product Type', category: 'shopify');
    }

    protected function options(): array
    {
        $values = ShopifyData::query()->where('type', 'Product')->distinct()->pluck('productType')->all();

        // Keep the selected values in the list, even if no product has them any more
        return collect([...$values, ...$this->getValues()])
            ->unique()
            ->map(fn($type) => ['value' => $type, 'label' => Str::title((string)$type)])
            ->values()
            ->all();
    }

    public function modifyQuery(Builder $query, ElementQueryInterface $elementQuery): void
    {
        ProductQuery::applyProductType($query, $this->paramValue());
    }

    public function matchElement(ElementInterface $element): bool
    {
        /** @var Product $element */
        return $this->matchValue($element->productType);
    }
}
