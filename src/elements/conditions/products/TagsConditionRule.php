<?php

namespace craft\shopify\elements\conditions\products;

use craft\base\conditions\BaseTextConditionRule;
use craft\base\ElementInterface;
use craft\elements\conditions\ElementConditionRuleInterface;
use craft\elements\db\ElementQueryInterface;
use craft\shopify\elements\db\ProductQuery;
use craft\shopify\elements\Product;

class TagsConditionRule extends BaseTextConditionRule implements ElementConditionRuleInterface
{
    /**
     * @inheritDoc
     */
    public function getLabel(): string
    {
        return \Craft::t('shopify', 'Tags');
    }

    /**
     * @inheritDoc
     */
    public function getExclusiveQueryParams(): array
    {
        return ['tags'];
    }

    /**
     * @inheritDoc
     * @param Product $element
     */
    public function matchElement(ElementInterface $element): bool
    {
        return $this->matchValue($element->tags);
    }

    /**
     * @inheritDoc
     */
    public function modifyQuery(ElementQueryInterface $query): void
    {
        /** @var ProductQuery $query */
        $query->tags($this->paramValue());
    }
}
