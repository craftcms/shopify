<?php

namespace craft\shopify\elements\conditions\products;

use craft\base\conditions\BaseMultiSelectConditionRule;
use craft\base\ElementInterface;
use craft\elements\conditions\ElementConditionRuleInterface;
use craft\elements\db\ElementQueryInterface;
use craft\helpers\StringHelper;
use craft\shopify\elements\db\ProductQuery;
use craft\shopify\elements\Product;
use craft\shopify\records\ProductData;

class ProductTypeConditionRule extends BaseMultiSelectConditionRule implements ElementConditionRuleInterface
{
    /**
     * @inheritDoc
     */
    public function getLabel(): string
    {
        return \Craft::t('shopify', 'Product Type');
    }

    /**
     * @inheritDoc
     */
    protected function options(): array
    {
        return collect(ProductData::find()->select('type')->distinct()->column())->map(function($type) {
            return ['value' => $type, 'label' => StringHelper::titleize($type)];
        })->all();
    }

    /**
     * @inheritDoc
     */
    public function getExclusiveQueryParams(): array
    {
        return ['productType'];
    }

    /**
     * @inheritDoc
     * @param Product $element
     */
    public function matchElement(ElementInterface $element): bool
    {
        return $this->matchValue($element->productType);
    }

    /**
     * @inheritDoc
     */
    public function modifyQuery(ElementQueryInterface $query): void
    {
        /** @var ProductQuery $query */
        $query->productType($this->paramValue());
    }
}
