<?php

namespace shopify\fieldtypes;

use Craft;
use craft\base\Field;
use craft\base\ElementInterface;
use craft\base\PreviewableFieldInterface;
use shopify\Shopify;

class ProductFieldType extends Field implements PreviewableFieldInterface
{
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
        $productOptions = array('limit' => 250);
        $products = Shopify::getInstance()->service->getProducts($productOptions);

        $options = array();
        foreach ($products as $product) {
            $options[] = array(
                'label' => $product['title'],
                'productId' => $product['id']
            );
        }

        return Craft::$app->getView()->renderTemplate('shopify/_select',
            [
                'name' => $this->handle,
                'value' => $value,
                'field' => $this,
                'products' => $options
            ]);
    }

}