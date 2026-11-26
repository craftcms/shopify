<?php

declare(strict_types=1);

namespace CraftCms\Shopify\Helpers;

use craft\shopify\models\Variant;
use CraftCms\Cms\Cp\Html\StatusHtml;
use CraftCms\Cms\Shared\Enums\Color;
use CraftCms\Cms\Support\DateTimeHelper;
use CraftCms\Cms\Support\Facades\I18N;
use CraftCms\Cms\Support\Html;
use CraftCms\Cms\Support\Str;
use CraftCms\Cms\Translation\Formatter;
use CraftCms\Shopify\Api\Models\ShopifyData;
use CraftCms\Shopify\Product\Elements\Product as ProductElement;
use DateTimeInterface;

use function CraftCms\Cms\t;

/**
 * Renders Shopify product details in the control panel.
 */
class Product
{
    /**
     * Returns the product’s Shopify details, for the edit screen’s metadata.
     *
     * @param string[] $exclude Keys to leave out: `variants`, `options` or `metafields`.
     * @return array<string, string|false>
     */
    public static function metadata(ProductElement $product, array $exclude = []): array
    {
        $formatter = I18N::getFormatter();
        $formatDate = fn(?DateTimeInterface $date) => $date ? $formatter->asDateTime($date, Formatter::FORMAT_WIDTH_SHORT) : false;
        $variants = $product->getVariants();
        $metafields = $product->getMetafields();

        $meta = [
            t('Shopify', category: 'shopify') => Html::a(
                t('Open in Shopify', category: 'shopify'),
                $product->getShopifyEditUrl(),
                ['target' => '_blank', 'data' => ['icon' => 'external']],
            ),
            t('Product Type', category: 'shopify') => $product->productType ? Html::encode($product->productType) : false,
            t('Handle', category: 'shopify') => Html::encode((string)$product->handle),
            t('Shopify Status', category: 'shopify') => self::shopifyStatusHtml($product),
            t('Options', category: 'shopify') => !in_array('options', $exclude, true) && $product->getOptions()
                ? collect($product->getOptions())
                    ->map(fn(array $option) => Html::tag('span', Html::encode($option['name']), [
                        'title' => t('{name} option values: {values}', [
                            'name' => $option['name'],
                            'values' => implode(', ', $option['values']),
                        ], category: 'shopify'),
                    ]))
                    ->join(', ')
                : false,
            t('Tags', category: 'shopify') => $product->getTags()
                ? Html::tag('div', collect($product->getTags())
                    ->map(fn(string $tag) => Html::tag('span', Html::encode($tag), ['class' => 'token']))
                    ->join(' '))
                : false,
            t('Total variants', category: 'shopify') => !in_array('variants', $exclude, true) && $variants->isNotEmpty()
                ? $formatter->asInteger($variants->count())
                : false,
            t('Variants', category: 'shopify') => !in_array('variants', $exclude, true) && $variants->isNotEmpty()
                ? $variants->map(fn(Variant $variant) => Html::encode($variant->title))->join(', ')
                : false,
            t('Meta fields', category: 'shopify') => !in_array('metafields', $exclude, true) && $metafields
                ? Html::encode(implode(', ', array_keys($metafields)))
                : false,
            t('Shopify ID', category: 'shopify') => Html::tag('code', (string)$product->shopifyId),
            t('Template suffix', category: 'shopify') => $product->templateSuffix
                ? Html::tag('code', Html::encode($product->templateSuffix))
                : false,
            t('Created in Shopify', category: 'shopify') => $formatDate($product->createdAt),
            t('Published in Shopify', category: 'shopify') => $formatDate($product->publishedAt),
            t('Updated in Shopify', category: 'shopify') => $formatDate($product->updatedAt),
        ];

        // The last time the product data was updated from Shopify by a webhook or sync
        $synced = ShopifyData::query()->where('shopifyGid', $product->shopifyGid)->value('dateUpdated');
        if ($synced) {
            $meta[t('Last synced', category: 'shopify')] = t('{duration} ago', [
                'duration' => DateTimeHelper::humanDuration(now()->diff(DateTimeHelper::toDateTime($synced)), false),
            ], category: 'shopify');
        }

        return $meta;
    }

    public static function shopifyStatusHtml(ProductElement $product): string
    {
        $color = match (Str::lower($product->shopifyStatus)) {
            ProductElement::SHOPIFY_STATUS_ACTIVE => Color::Green,
            ProductElement::SHOPIFY_STATUS_ARCHIVED => Color::Red,
            default => Color::Orange,
        };

        return (string)app(StatusHtml::class)->statusLabelHtml([
            'color' => $color->value,
            'label' => Str::title($product->shopifyStatus),
        ]);
    }
}
