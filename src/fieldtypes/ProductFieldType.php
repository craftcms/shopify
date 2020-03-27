<?php

namespace shopify\fieldtypes;

use Craft;
use craft\base\Field;
use craft\base\ElementInterface;
use craft\base\PreviewableFieldInterface;
use GraphQL\Type\Definition\Type;
use shopify\Shopify;
use shopify\ShopifyAssets;

class ProductFieldType extends Field implements PreviewableFieldInterface
{
    /**
     * @return array|\GraphQL\Type\Definition\ListOfType|\GraphQL\Type\Definition\StringType|\GraphQL\Type\Definition\Type
     */
    public function getContentGqlType()
    {
        return Type::listOf(Type::string());
    }

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
        /** @var \shopify\models\Settings $settings */
        $settings = \shopify\Shopify::getInstance()->getSettings();

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

        Craft::$app->getView()->registerAssetBundle(ShopifyAssets::class);

        $instanceId = str_replace('.', '', uniqid('', true));
        return Craft::$app->getView()->renderTemplate('shopify/_select', [
            'wrapper_class' => $settings->wrapperClass,
            'instance_wrapper_class' => $settings->wrapperClass . '-' . $instanceId,
            'name' => $this->handle,
            'value' => $value,
            'field' => $this,
            'options' => $options,
            'type' => 'products',
        ]);
    }
}
