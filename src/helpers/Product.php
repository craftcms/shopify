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

/**
 * Shopify Product Helper.
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 1.0
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
            'class' => 'pec-title'
        ]);
        $subTitle = Html::tag('p', 'Clothing' . $product->productType, [
            'class' => 'pec-subtitle'
        ]);
        $extenalLink = Html::tag('div', '&nbsp;', [
            'class' => 'pec-external-icon',
            'data' => [
                'icon' => 'external'
            ]
        ]);
        $cardHeader = Html::a($title . $subTitle . $extenalLink, $product->getShopifyEditUrl(), [
            'style' => '',
            'class' => 'pec-header',
            'target' => '_blank',
            'title' => Craft::t('shopify', 'Open in Shopify'),
        ]);

        $hr = Html::tag('hr', '', [
            'class' => ''
        ]);

        $meta = [];
//        $meta['Images'] = collect($product->images)->pluck('src_old')->map(static function($image){
//          return Html::img($image,[
//              'width' => 25,
//              'height' => 25,
//              'style' => 'margin-left:6px'
//          ]);
//        })->join("\n");
        $meta[Craft::t('shopify', 'Handle')] = $product->handle;
        $meta[Craft::t('shopify', 'Status')] = StringHelper::titleize($product->status);
        $meta[Craft::t('shopify', 'Options')] = collect($product->options)->pluck('name')->join(', ');
        $meta[Craft::t('shopify', 'Shopify ID')] = $product->shopifyId;
        $meta[Craft::t('shopify', 'Shopify Handle')] = $product->handle;
        $meta[Craft::t('shopify', 'Product Type')] = $product->productType;
        $meta[Craft::t('shopify', 'Created At')] = $formatter->asDatetime($product->createdAt, Formatter::FORMAT_WIDTH_SHORT);
        $meta[Craft::t('shopify', 'Published At')] = $formatter->asDatetime($product->publishedAt, Formatter::FORMAT_WIDTH_SHORT);
        $meta[Craft::t('shopify', 'Updated At')] = $formatter->asDatetime($product->updatedAt, Formatter::FORMAT_WIDTH_SHORT);

        $metadataHtml = Cp::metadataHtml($meta);

        $spinner = Html::tag('div', '', [
            'class' => 'spinner',
            'hx' => [
                'indicator'
            ]
        ]);

        $footer = Html::tag('div', 'Updated from Shopify 15 seconds ago.' . $spinner, [
            'class' => 'pec-footer'
        ]);

        return Html::tag('div', $cardHeader . $hr . $metadataHtml . $footer, [
            'class' => 'meta proxy-element-card',
            'id' => 'pec-' . $product->id,
            'hx' => [
                'get' => UrlHelper::actionUrl('shopify/products/render-card-html', [
                    'id' => $product->id
                ]),
                'swap'=> 'outerHTML',
                'trigger' => 'every 15s'
            ]
        ]);
    }

}