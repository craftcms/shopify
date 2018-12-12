<?php

namespace shopify\fieldtypes;

use Craft;
use craft\base\Field;
use craft\base\ElementInterface;
use craft\base\PreviewableFieldInterface;
use shopify\Shopify;

class ProductFieldType extends Field implements PreviewableFieldInterface
{
    // Properties
    // =========================================================================

    /**
     * @var bool Whether the field should support multiple selections
     */
    public $multi = false;

    /**
     * @var bool Array of products from Shopify API
     */
    public $products = false;

    // Public Methods
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
     * @inheritdoc
     */
    public function getSettingsHtml()
    {
        return Craft::$app->getView()->renderTemplateMacro('_includes/forms', 'lightswitchField',
            [
                [
                    'label' => Craft::t('shopify', 'Allow multiple selections?'),
                    'id' => 'multi',
                    'name' => 'multi',
                    'on' => $this->multi,
                ]
            ]
        );
    }

    /**
     * Returns an array of all products from the Shopify API
     *
     * @return array
     */
    public function getProducts()
    {
        if (!$this->products) {
          $this->products = Shopify::getInstance()->service->getProducts([
              'limit' => 250,
          ]);
        }

        return $this->products;
    }

    /**
     * Returns the template-partial an editor sees when editing plugin-content on a page
     *
     * @param $value
     * @param ElementInterface|null $element
     * @return string
     * @throws \Twig_Error_Loader
     * @throws \yii\base\Exception
     */
    public function getInputHtml($value, ElementInterface $element = null): string
    {
        $products = $this->getProducts();

        $options = [];
        if ($products) {
            foreach ($products as $product) {
                $options[$product['id']] = $product['title'];
            }
        }

        if ($this->multi) {
            return Craft::$app->getView()->renderTemplateMacro('_includes/forms', 'multiselectField',
                [
                    [
                        'name' => $this->handle,
                        'values' => $value,
                        'options' => $options,
                    ]
                ]
            );
        } else {
            array_prepend($options, '');
            return Craft::$app->getView()->renderTemplateMacro('_includes/forms', 'selectField',
                [
                    [
                        'id' => $this->handle,
                        'name' => $this->handle.'[]',
                        'value' => $value,
                        'options' => $options,
                    ]
                ]
            );
        }
    }

    /**
     * @inheritdoc
     */
    public function getTableAttributeHtml($value, ElementInterface $element): string
    {
        $settings = Shopify::getInstance()->getSettings();
        $products = $this->getProducts();

        $value = json_decode($value);
        $value = is_array($value) ? $value : [$value];

        $selected = [];
        foreach ($products as $product) {
            if (in_array($product['id'], $value)) {
                $link = "https://{$settings->hostname}/admin/products/{$product['id']}";
                $selected[] = "<a href=\"{$link}\" target=\"_blank\">{$product['title']}</a>";
            }
        }

        return implode(', ', $selected);
    }
}
