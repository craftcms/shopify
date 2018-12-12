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
        $productOptions = ['limit' => 250];
        $products = Shopify::getInstance()->service->getProducts($productOptions);

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
          return Craft::$app->getView()->renderTemplateMacro('_includes/forms', 'selectField',
              [
                  [
                      'name' => $this->handle,
                      'value' => $value,
                      'options' => $options,
                  ]
              ]
          );
        }
    }

}
