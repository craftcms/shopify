<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\shopify\helpers;

use Craft;
use craft\helpers\Cp;
use craft\helpers\DateTimeHelper;
use craft\helpers\Html;
use craft\helpers\StringHelper;
use craft\helpers\UrlHelper;
use craft\i18n\Formatter;
use craft\shopify\elements\Product as ProductElement;
use craft\shopify\records\ProductData;

/**
 * Shopify Product Helper.
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 3.0
 */
class Product
{
    /**
     * @return string
     */
    public static function renderCardHtml(ProductElement $product): string
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
        $meta[Craft::t('shopify', 'Status')] = $product->getShopifyStatusHtml();

        // Options
        if (count($product->getOptions()) > 0) {
            $meta[Craft::t('shopify', 'Options')] =
                collect($product->options)->map(function($option) {
                    return Html::tag('span', $option['name'], [
                        'title' => $option['name'] . ' option values: ' . collect($option['values'])->join(', '),
                    ]);
                })->join(',&nbsp;');
        }

        // Tags
        if (count($product->tags) > 0) {
            $tags = collect($product->tags)->map(function($tag) {
                return Html::tag('span', $tag, [
                    'class' => 'token',
                ]);
            })->join('&nbsp;');

            $meta[Craft::t('shopify', 'Tags')] = Html::tag('div', $tags);
        }

        // Variants
        if (count($product->getVariants()) > 0) {
            $meta[Craft::t('shopify', 'Variants')] = collect($product->getVariants())->pluck('title')->map(fn($title) => StringHelper::toTitleCase($title))->join(',&nbsp;');
        }

        $meta[Craft::t('shopify', 'Shopify ID')] = $product->shopifyId;

        $meta[Craft::t('shopify', 'Created at')] = $formatter->asDatetime($product->createdAt, Formatter::FORMAT_WIDTH_SHORT);
        $meta[Craft::t('shopify', 'Published at')] = $formatter->asDatetime($product->publishedAt, Formatter::FORMAT_WIDTH_SHORT);
        $meta[Craft::t('shopify', 'Updated at')] = $formatter->asDatetime($product->updatedAt, Formatter::FORMAT_WIDTH_SHORT);

        $metadataHtml = Cp::metadataHtml($meta);

        $spinner = Html::tag('div', '', [
            'class' => 'spinner',
            'hx' => [
                'indicator',
            ],
        ]);

        // This is the date updated in the database which represents the last time it was updated from a Shopify webhook or sync.
        /** @var ProductData $productData */
        $productData = ProductData::find()->where(['shopifyId' => $product->shopifyId])->one();
        $dateUpdated = DateTimeHelper::toDateTime($productData->dateUpdated);
        $now = new \DateTime();
        $diff = $now->diff($dateUpdated);
        $duration = DateTimeHelper::humanDuration($diff, false);
        $footer = Html::tag('div', 'Updated ' . $duration . ' ago.' . $spinner, [
            'class' => 'pec-footer',
        ]);

        return Html::tag('div', $cardHeader . $hr . $metadataHtml . $footer, [
            'class' => 'meta proxy-element-card',
            'id' => 'pec-' . $product->id,
            'hx' => [
                'get' => UrlHelper::actionUrl('shopify/products/render-card-html', [
                    'id' => $product->id,
                ]),
                'swap' => 'outerHTML',
                'trigger' => 'every 15s',
            ],
        ]);
    }
}
