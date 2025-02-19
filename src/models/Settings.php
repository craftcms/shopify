<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\shopify\models;

use Craft;
use craft\base\Model;
use craft\helpers\App;
use craft\helpers\UrlHelper;
use craft\shopify\elements\Product;
use craft\shopify\Plugin;
use Shopify\ApiVersion;

/**
 * Shopify Settings model.
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 3.0
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

    /**
     * @var string|null Comma separated list of country codes to use for contextual pricing.
     */
    private ?string $_contextualPricingCountries = null;

    /**
     * @var string The Shopify API version to use.
     * @see setApiVersion()
     * @see getApiVersion()
     */
    private string $_apiVersion = ApiVersion::OCTOBER_2024;

    /**
     * Whether product metafields should be included when syncing products. This adds an extra API request per product.
     *
     * @var bool
     * @since 4.1.0
     * @deprecated in 6.0.0. This data is now automatically included when syncing products.
     */
    public bool $syncProductMetafields = true;

    /**
     * Whether variant metafields should be included when syncing products. This adds an extra API request per variant.
     *
     * @var bool
     * @since 4.1.0
     * @deprecated in 6.0.0. This data is now automatically included when syncing products.
     */
    public bool $syncVariantMetafields = false;

    public function rules(): array
    {
        return [
            [['apiSecretKey', 'apiKey', 'accessToken', 'hostName', 'apiVersion'], 'required'],
            [['apiVersion'], 'in', 'range' => Plugin::getInstance()->getApi()->getSupportedApiVersions()],
            [['contextualPricingCountries'], 'safe'],
        ];
    }

    public function attributes()
    {
        $names = parent::attributes();
        $names[] = 'apiVersion';
        $names[] = 'contextualPricingCountries';

        return $names;
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels(): array
    {
        return [
            'apiKey' => Craft::t('shopify', 'Shopify API Key'),
            'apiSecretKey' => Craft::t('shopify', 'Shopify API Secret Key'),
            'apiVersion' => Craft::t('shopify', 'Shopify API Version'),
            'accessToken' => Craft::t('shopify', 'Shopify Access Token'),
            'contextualPricingCountries' => Craft::t('shopify', 'Context Pricing Countries'),
            'hostName' => Craft::t('shopify', 'Shopify Host Name'),
            'uriFormat' => Craft::t('shopify', 'Product URI format'),
            'template' => Craft::t('shopify', 'Product Template'),
        ];
    }

    /**
     * @param string $apiVersion
     * @return void
     * @since 5.3.0
     */
    public function setApiVersion(string $apiVersion): void
    {
        $this->_apiVersion = $apiVersion;
    }

    /**
     * @param bool $parse
     * @return string
     * @since 5.3.0
     */
    public function getApiVersion(bool $parse = true): string
    {
        return $parse ? App::parseEnv($this->_apiVersion) : $this->_apiVersion;
    }

    /**
     * @param string $contextualPricingCountries
     * @return void
     * @since 6.0.0
     */
    public function setContextualPricingCountries(string $contextualPricingCountries): void
    {
        $this->_contextualPricingCountries = $contextualPricingCountries;
    }

    /**
     * @param bool $parse
     * @return string
     * @since 6.0.0
     */
    public function getContextualPricingCountries(bool $parse = true): string
    {
        return ($parse ? App::parseEnv($this->_contextualPricingCountries) : $this->_contextualPricingCountries) ?? '';
    }

    /**
     * @return \craft\models\FieldLayout|mixed
     */
    public function getProductFieldLayout()
    {
        if (!isset($this->_productFieldLayout)) {
            $this->_productFieldLayout = Craft::$app->fields->getLayoutByType(Product::class);
        }

        return $this->_productFieldLayout;
    }

    /**
     * @param mixed $fieldLayout
     * @return void
     */
    public function setProductFieldLayout(mixed $fieldLayout): void
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
