<?php

namespace shopify\fieldtypes;

use Craft;
use craft\base\Field;
use craft\base\ElementInterface;
use craft\base\PreviewableFieldInterface;
use shopify\Shopify;

class ProductFieldType extends Field implements PreviewableFieldInterface
{
    /**
     * @param $value
     * @return mixed
     */
    public function normalizeValue($value, ElementInterface $element = null)
    {
        if (is_array($value)) {
            return $value;
        }
        return json_decode($value);
    }

    // Static Methods
    // =========================================================================

    /**
     * Returns the display name of this class.
     *
     * @return string The display name of this class.
     */
    public static function displayName(): string
    {
        //category is the filename inside ./translations/en/
        return Craft::t('shopify', 'Shopify Product');
    }

    /**
     * returns the template-partial an editor sees when editing plugin-content on a page
     *
     * @param $value
     * @param ElementInterface|null $element
     * @return string
     * @throws \Twig_Error_Loader
     * @throws \yii\base\Exception
     */
    public function getInputHtml($value, ElementInterface $element = null): string
    {
        $defaultOptions = [];
        $count = Shopify::getInstance()->service->getProductsCount($defaultOptions);
        $productsData = Shopify::getInstance()->service->getProducts($defaultOptions);

        $products = [];
        if ($productsData['products'] && count($productsData['products']) > 0) {
            $products = array_merge_recursive($products, $productsData['products']);
        }

        if (count($products) < $count && $productsData['link']['url']) {
            while (count($products) < $count) {
                $nextLink = $productsData['link']['url'];
                $productsData = Shopify::getInstance()->service->getProducts($defaultOptions, $nextLink);
                if ($productsData['products'] && count($productsData['products']) > 0) {
                    $products = array_merge_recursive($products, $productsData['products']);
                }
            }
        }

        $options = [];
        if ($products) {
            foreach ($products as $product) {
                $options[] = array(
                    'label' => $product['title'],
                    'productId' => $product['id'],
                    'sku' => implode(
                        ', ',
                        array_map(function ($variant) {
                            return $variant['sku'];
                        }, $product['variants'])
                    ),
                );
            }
        }

        return Craft::$app->getView()->renderTemplate('shopify/_select', [
            'filter_class' => $this->handle . '_filter',
            'selected_only_class' => $this->handle . '_selected_only',
            'clear_selected_class' => $this->handle . '_clear_selected',
            'wrapper_class' => $this->handle . '_wrapper',
            'name' => $this->handle,
            'value' => $value,
            'field' => $this,
            'products' => $options,
        ]);
    }
}
