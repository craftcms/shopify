<?php
namespace shopify\utilities\feedme;

use verbb\feedme\base\Field;
use verbb\feedme\base\FieldInterface;

use Craft;

use Cake\Utility\Hash;

class Shopify extends Field implements FieldInterface
{
    // Properties
    // =========================================================================

    public static $name = 'Shopify';
    public static $class = 'shopify\fieldtypes\ProductFieldType';

    protected $products = false;


    // Templates
    // =========================================================================

    public function getMappingTemplate()
    {
        return 'shopify/feedme/field-settings';
    }


    // Public Methods
    // =========================================================================

    /**
     * Returns an array of all products from the Shopify API
     *
     * @return array
     */
    public function getProducts()
    {
        if (!$this->products) {
            $this->products = \shopify\Shopify::getInstance()->service->getProducts(
                [
                'limit' => 250,
                ]
            );
        }

        return $this->products;
    }

    public function parseField()
    {
        $value = $this->fetchArrayValue();
        $products = $this->getProducts();

        $match = Hash::get($this->fieldInfo, 'options.match', 'value');

        $preppedData = [];
        foreach ($products as $product) {
            foreach ($value as $dataValue) {
                if ($dataValue === $product[$match]) {
                    $preppedData[] = $product['id'];
                }
            }
        }

        return $preppedData;
    }

}
