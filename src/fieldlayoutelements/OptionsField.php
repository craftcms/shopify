<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\shopify\fieldlayoutelements;

use Craft;
use craft\base\ElementInterface;
use craft\enums\Color;
use craft\fieldlayoutelements\BaseNativeField;
use craft\helpers\Cp;
use craft\helpers\Html;
use craft\shopify\elements\Product;
use yii\base\InvalidArgumentException;

/**
 * OptionsField represents an Options field that can be included within a product’s product field layout designer.
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 7.0.0
 */
class OptionsField extends BaseNativeField
{
    /**
     * @inheritdoc
     */
    public string $attribute = 'options';

    /**
     * @inheritdoc
     */
    public function hasCustomWidth(): bool
    {
        return false;
    }

    /**
     * @inheritdoc
     */
    protected function defaultLabel(ElementInterface $element = null, bool $static = false): ?string
    {
        return Craft::t('shopify', 'Options');
    }

    /**
     * @inheritdoc
     */
    protected function inputHtml(ElementInterface $element = null, bool $static = false): ?string
    {
        if (!$element instanceof Product) {
            throw new InvalidArgumentException(__CLASS__ . ' can only be used in product field layouts.');
        }

        $options = $element->getOptions();

        if (empty($options)) {
            return Html::beginTag('div', ['class' => 'zilch']) .
                Html::tag('p', Craft::t('shopify', 'This product has no options.')) .
                Html::endTag('div');
        }

        $cols = [
            'option' => ['heading' => Craft::t('shopify', 'Option'), 'type' => 'html'],
            'values' => ['heading' => Craft::t('shopify', 'Values'), 'type' => 'html'],
            'hasVariants' => ['heading' => Craft::t('shopify', 'Has variants'), 'type' => 'html'],
        ];

        $tableData = [];
        foreach ($options as $opt) {
            foreach ($opt['optionValues'] as $i =>  $val) {
                $tableData[] = [
                    'option' => $i === 0 ? Html::tag('strong', Html::encode($opt['name'])) : '',
                    'values' => Html::encode($val['name']),
                    'hasVariants' => (bool)$val['hasVariants'] ? Html::tag('div', Cp::iconSvg('check'), [
                        'class' => array_filter(['thumb', 'cp-icon', Color::Green->value]),
                    ]) : '',
                ];
            }
        }

        return Cp::editableTableHtml([
            'id' => $this->id(),
            'name' => $this->baseInputName(),
            'cols' => $cols,
            'rows' => $tableData,
            'static' => true,
        ]);
    }
}
