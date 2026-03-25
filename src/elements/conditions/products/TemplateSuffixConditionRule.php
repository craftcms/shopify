<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\shopify\elements\conditions\products;

use craft\base\conditions\BaseTextConditionRule;
use craft\base\ElementInterface;
use craft\elements\conditions\ElementConditionRuleInterface;
use craft\elements\db\ElementQueryInterface;
use craft\shopify\elements\db\ProductQuery;
use craft\shopify\elements\Product;

/**
 * Template Suffix Condition Rule
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 7.0.0
 */
class TemplateSuffixConditionRule extends BaseTextConditionRule implements ElementConditionRuleInterface
{
    /**
     * @inheritDoc
     */
    public function getLabel(): string
    {
        return \Craft::t('shopify', 'Template suffix');
    }

    /**
     * @inheritDoc
     */
    public function getExclusiveQueryParams(): array
    {
        return ['templateSuffix'];
    }

    /**
     * @inheritDoc
     * @param Product $element
     */
    public function matchElement(ElementInterface $element): bool
    {
        return $this->matchValue($element->templateSuffix);
    }

    /**
     * @inheritDoc
     * @param ProductQuery $query
     */
    public function modifyQuery(ElementQueryInterface $query): void
    {
        /** @var ProductQuery $query */
        $query->templateSuffix($this->paramValue());
    }
}
