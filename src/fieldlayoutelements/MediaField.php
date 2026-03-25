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
use craft\helpers\Html;
use craft\shopify\elements\Product;
use yii\base\InvalidArgumentException;

/**
 * MediaField represents a Media field that can be included within a product’s product field layout designer.
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 7.0.0
 */
class MediaField extends BaseNativeField
{
    /**
     * @inheritdoc
     */
    public string $attribute = 'images';

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
        return Craft::t('shopify', 'Media');
    }

    /**
     * @inheritdoc
     */
    protected function inputHtml(ElementInterface $element = null, bool $static = false): ?string
    {
        if (!$element instanceof Product) {
            throw new InvalidArgumentException(__CLASS__ . ' can only be used in product field layouts.');
        }

        $media = $element->getImages();

        if (empty($media)) {
            return Html::beginTag('div', ['class' => 'zilch']) .
                Html::tag('p', Craft::t('shopify', 'This product has no media.')) .
                Html::endTag('div');
        }

        $html =
            Html::beginTag('div', ['class' => 'elements']) .
            Html::beginTag('ul', ['class' => 'thumbsview']);

        foreach ($media as $item) {
            $html .=
                Html::beginTag('li') .
                    Html::beginTag('div', ['class' => 'chip large element']) .
                        Html::tag('div', Html::img($item['image']['url']), ['class' => 'thumb']) .
                    Html::endTag('div') .
                Html::endTag('li');
        }

        $html .= Html::endTag('ul') .
            Html::endTag('div');

        return $html;
    }
}
