<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\shopify\fieldlayoutelements;

use Craft;
use craft\base\ElementInterface;
use craft\fieldlayoutelements\BaseNativeField;
use craft\helpers\Cp;
use craft\helpers\Html;
use craft\shopify\elements\Product;
use yii\base\InvalidArgumentException;

/**
 * MetaFieldsField represents a MetaFields field that can be included within a product’s product field layout designer.
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 7.0.0
 */
class MetafieldsField extends BaseNativeField
{
    /**
     * @inheritdoc
     */
    public string $attribute = 'metafields';

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
        return Craft::t('shopify', 'Meta fields');
    }

    /**
     * @inheritdoc
     */
    protected function inputHtml(ElementInterface $element = null, bool $static = false): ?string
    {
        if (!$element instanceof Product) {
            throw new InvalidArgumentException(__CLASS__ . ' can only be used in product field layouts.');
        }

        $metafields = $element->getMetafields();

        if (empty($metafields)) {
            return Html::beginTag('div', ['class' => 'zilch']) .
                Html::tag('p', Craft::t('shopify', 'This product has no meta fields.')) .
                Html::endTag('div');
        }

        $cols = [
            'key' => ['heading' => Craft::t('shopify', 'Key'), 'type' => 'html'],
            'value' => ['heading' => Craft::t('shopify', 'Value'), 'type' => 'html'],
        ];

        $tableData = [];
        foreach ($metafields as $key => $value) {
            $tableData[] = [
                'key' => Html::tag('code', Html::encode($key)),
                'value' => Html::tag('code', json_encode($value)),
            ];
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
