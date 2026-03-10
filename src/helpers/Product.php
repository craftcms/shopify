<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\shopify\helpers;

use Craft;
use craft\enums\Color;
use craft\helpers\Cp;
use craft\helpers\DateTimeHelper;
use craft\helpers\Html;
use craft\helpers\StringHelper;
use craft\i18n\Formatter;
use craft\shopify\elements\Product as ProductElement;
use craft\shopify\models\Variant;
use craft\shopify\records\ShopifyData;
use yii\base\InvalidConfigException;

/**
 * Shopify Product Helper.
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 3.0
 */
class Product
{
    /**
     * @param ProductElement $product
     * @param array $excludeMetaDataKeys
     * @return string
     * @throws InvalidConfigException
     */
    public static function renderCardHtml(ProductElement $product, array $excludeMetaDataKeys = []): string
    {
        $formatter = Craft::$app->getFormatter();

        $title = Html::tag('h3', $product->title, [
            'class' => 'pec-title',
        ]);

        $subTitle = Html::tag('p', $product->productType, [
            'class' => 'pec-subtitle',
        ]);
        $externalLink = Html::tag('div', '&nbsp;', [
            'class' => 'pec-external-icon',
            'data' => [
                'icon' => 'external',
            ],
        ]);
        $cardHeader = Html::a($title . $subTitle . $externalLink, $product->getShopifyEditUrl(), [
            'style' => '',
            'class' => 'pec-header',
            'target' => '_blank',
            'title' => Craft::t('shopify', 'Open in Shopify'),
        ]);

        $hr = Html::tag('hr', '', [
            'class' => '',
        ]);

        $meta = [];

        $meta[Craft::t('shopify', 'Handle')] = $product->handle;
        $meta[Craft::t('shopify', 'Status')] = Product::shopifyStatusHtml($product);

        // Options
        if (count($product->getOptions()) > 0) {
            $meta[Craft::t('shopify', 'Options')] = collect($product->options)
                ->map(function($option) {
                    return Html::tag('span', $option['name'], [
                        'title' => Craft::t('shopify', '{name} option values: {values}', [
                            'name' => $option['name'],
                            'values' => join(', ', $option['values']),
                        ]),
                    ]);
                })
                ->join(', ');
        }

        // Tags
        if (count($product->tags) > 0) {
            $tags = collect($product->tags)
                ->map(function($tag) {
                    return Html::tag('span', $tag, [
                        'class' => 'token',
                    ]);
                })
                ->join(' ');

            $meta[Craft::t('shopify', 'Tags')] = Html::tag('div', $tags);
        }

        // Variants
        $variants = $product->getVariants();
        if (count($variants) > 0) {
            $meta[Craft::t('shopify', 'Total variants')] = Craft::$app->getFormatter()->asInteger(count($variants));

            $meta[Craft::t('shopify', 'Variants')] = collect($variants)
                ->map(fn(Variant $variant) => Html::encode($variant->title))
                ->join(', ');
        }

        // Metafields
        if (count($product->getMetafields()) > 0) {
            $meta[Craft::t('shopify', 'Meta fields')] = collect($product->getMetafields())
                ->keys()
                ->join(', ');
        }

        $meta[Craft::t('shopify', 'Shopify ID')] = Html::tag('code', (string)$product->shopifyId);

        // Template suffix
        if (!empty($product->templateSuffix)) {
            $meta[Craft::t('shopify', 'Template suffix')] = Html::tag('code', $product->templateSuffix);
        }

        $meta[Craft::t('shopify', 'Created at')] = $formatter->asDatetime($product->createdAt, Formatter::FORMAT_WIDTH_SHORT);
        $meta[Craft::t('shopify', 'Published at')] = $formatter->asDatetime($product->publishedAt, Formatter::FORMAT_WIDTH_SHORT);
        $meta[Craft::t('shopify', 'Updated at')] = $formatter->asDatetime($product->updatedAt, Formatter::FORMAT_WIDTH_SHORT);

        foreach ($excludeMetaDataKeys as $key) {
            if (array_key_exists($key, $meta)) {
                unset($meta[$key]);
            }
        }

        $metadataHtml = Cp::metadataHtml($meta);

        // This is the date updated in the database which represents the last time it was updated from a Shopify webhook or sync.
        /** @var ShopifyData $productData */
        $productData = ShopifyData::find()->where(['shopifyId' => $product->shopifyGid])->one();
        $dateUpdated = DateTimeHelper::toDateTime($productData->dateUpdated);
        $now = new \DateTime();
        $diff = $now->diff($dateUpdated);
        $duration = DateTimeHelper::humanDuration($diff, false);
        $footer = Html::tag('div', 'Updated ' . $duration . ' ago.', [
            'class' => 'pec-footer',
        ]);

        return Html::tag('div', $cardHeader . $hr . $metadataHtml . $footer, [
            'class' => 'meta proxy-element-card',
            'id' => 'pec-' . $product->id,
        ]);
    }

    /**
     * @param ProductElement $product
     * @return string
     * @since 6.0.0
     */
    public static function shopifyStatusHtml(ProductElement $product): string
    {
        // @TODO update this either when Craft 4 support is dropped or 4 gets enums
        if (!class_exists(Color::class) || !method_exists(Cp::class, 'statusLabelHtml')) {
            $color = match (StringHelper::toLowerCase($product->shopifyStatus)) {
                ProductElement::SHOPIFY_STATUS_ACTIVE => 'green',
                ProductElement::SHOPIFY_STATUS_ARCHIVED => 'red',
                default => 'orange', // takes care of draft
            };
            return "<span class='status $color'></span>" . StringHelper::titleize($product->shopifyStatus);
        }

        $color = match (StringHelper::toLowerCase($product->shopifyStatus)) {
            ProductElement::SHOPIFY_STATUS_ACTIVE => Color::Green->value,
            ProductElement::SHOPIFY_STATUS_ARCHIVED => Color::Red->value,
            default => Color::Orange->value, // takes care of draft
        };

        return Cp::statusLabelHtml([
            'color' => $color,
            'label' => StringHelper::titleize($product->shopifyStatus),
        ]);
    }
}
