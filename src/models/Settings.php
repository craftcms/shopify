<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\shopify\models;

use Craft;
use craft\base\Model;
use craft\commerce\fieldlayoutelements\VariantsField;
use craft\helpers\ArrayHelper;
use craft\helpers\StringHelper;
use craft\helpers\UrlHelper;
use craft\models\FieldLayoutTab;
use craft\shopify\elements\Product;
use craft\shopify\fieldlayoutelements\ShopifyInformationField;

/**
 * Shopify Settings model.
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 1.0
 */
class Settings extends Model
{
    public string $apiKey = '';
    public string $apiSecretKey = '';
    public string $accessToken = '';
    public string $hostName = '';
    public string $uriFormat = '';
    public string $template = '';
    private mixed $_productFieldLayout;

    public function rules(): array
    {
        return [
            [['apiSecretKey', 'apiKey', 'accessToken', 'hostName'], 'required'],
        ];
    }

    public function getProductFieldLayout()
    {
        if (!isset($this->_productFieldLayout)) {
            $this->_productFieldLayout = Craft::$app->fields->getLayoutByType(Product::class);
        }

        if (!$this->_productFieldLayout->isFieldIncluded('shopifyInformation')) {
            $layoutTabs = $this->_productFieldLayout->getTabs();
            $shopifyTabName = Craft::t('shopify', 'Shopify');
            if (ArrayHelper::contains($layoutTabs, 'name', $shopifyTabName)) {
                $shopifyTabName .= ' ' . StringHelper::randomString(10);
            }
            $contentTab = new FieldLayoutTab([
                'name' => $shopifyTabName,
                'layout' => $this->_productFieldLayout,
                'elements' => [
                    [
                        'type' => ShopifyInformationField::class,
                    ],
                ],
            ]);
            array_unshift($layoutTabs, $contentTab);
            $this->_productFieldLayout->setTabs($layoutTabs);
        }

        return $this->_productFieldLayout;
    }

    /**
     * @param mixed $fieldLayout
     * @return void
     */
    public function setProductFieldLayout(mixed $fieldLayout)
    {
        $this->_productFieldLayout = $fieldLayout;
    }

    /**
     * @return string
     */
    public function getWebhookUrl(): string
    {
        return UrlHelper::actionUrl('shopify/webhook/handle');
    }
}
