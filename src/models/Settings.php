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
use craft\helpers\StringHelper;
use craft\helpers\UrlHelper;
use craft\shopify\elements\Product;
use craft\shopify\Plugin;
use Shopify\ApiVersion;
use Shopify\Utils;

/**
 * Shopify Settings model.
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 3.0
 */
class Settings extends Model
{
    private string $_clientId = '';
    private string $_clientSecret = '';

    private string $_hostName = '';
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
    private string $_apiVersion = ApiVersion::OCTOBER_2025;

    /**
     * Whether product metafields should be included when syncing products. This adds an extra API request per product.
     *
     * @var bool
     * @since 4.1.0
     * @deprecated in 6.0.0.
     */
    public bool $syncProductMetafields = true;

    /**
     * Whether variant metafields should be included when syncing products. This adds an extra API request per variant.
     *
     * @var bool
     * @since 4.1.0
     * @deprecated in 6.0.0.
     */
    public bool $syncVariantMetafields = false;

    public function rules(): array
    {
        return [
            [['clientSecret', 'clientId', 'hostName', 'apiVersion'], 'required'],
            [['apiVersion'], 'in', 'range' => Plugin::getInstance()->getApi()->getSupportedApiVersions()],
            [['hostName'], function($attribute) {
                $hostName = $this->$attribute;

                if (Utils::sanitizeShopDomain($hostName) === null) {
                    $this->addError($attribute,Craft::t('Shopify', 'The host name must be a valid Shopify store domain.'));
                }
            }, 'skipOnEmpty' => true],
        ];
    }

    public function attributes()
    {
        $names = parent::attributes();
        $names[] = 'apiVersion';
        $names[] = 'clientId';
        $names[] = 'clientSecret';
        $names[] = 'contextualPricingCountries';
        $names[] = 'hostName';
        $names[] = 'uriFormat';
        $names[] = 'template';

        return $names;
    }

    public function fields(): array
    {
        return [
            'apiVersion' => fn() => $this->getApiVersion(false),
            'clientId' => fn() => $this->getClientId(false),
            'clientSecret' => fn() => $this->getClientSecret(false),
            'contextualPricingCountries' => fn() => $this->getContextualPricingCountries(false),
            'hostName' => fn() => $this->getHostName(false),
            'uriFormat' => 'uriFormat',
            'template' => 'template',
        ];
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels(): array
    {
        return [
            'authUrl' => Craft::t('shopify', 'Shopify App Auth URL'),
            'clientId' => Craft::t('shopify', 'Shopify Client ID'),
            'clientSecret' => Craft::t('shopify', 'Shopify Client Secret Key'),
            'apiVersion' => Craft::t('shopify', 'Shopify API Version'),
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
        return ($parse ? App::parseEnv($this->_apiVersion) : $this->_apiVersion) ?? '';
    }

    /**
     * @param string $apiKey
     * @return void
     * @since 6.0.0
     * @deprecated in 7.0.0. Use [[setClientId()]] instead.
     */
    public function setApiKey(string $apiKey): void
    {
        Craft::$app->getDeprecator()->log(__METHOD__, '`setApiKey()` method has been deprecated. Use `setClientId()` instead.');
        return;
    }

    /**
     * @param bool $parse
     * @return string
     * @since 6.0.0
     * @deprecated in 7.0.0. Use [[getClientId()]] instead.
     */
    public function getApiKey(bool $parse = true): string
    {
        Craft::$app->getDeprecator()->log(__METHOD__, '`getApiKey()` method has been deprecated. Use `getClientId()` instead.');
        return $this->getClientId($parse);
    }

    /**
     * @param string $clientId
     * @return void
     * @since 7.0.0
     */
    public function setClientId(string $clientId): void
    {
        $this->_clientId = $clientId;
    }

    /**
     * @param bool $parse
     * @return string
     * @since 7.0.0
     */
    public function getClientId(bool $parse = true): string
    {
        return ($parse ? App::parseEnv($this->_clientId) : $this->_clientId) ?? '';
    }

    /**
     * @param string $apiSecretKey
     * @return void
     * @since 6.0.0
     * @deprecated in 7.0.0. Use [[setClientSecret()]] instead.
     */
    public function setApiSecretKey(string $apiSecretKey): void
    {
        Craft::$app->getDeprecator()->log(__METHOD__, '`setApiSecretKey()` method has been deprecated. Use `setClientSecret()` instead.');
        return;
    }

    /**
     * @param bool $parse
     * @return string
     * @since 6.0.0
     * @deprecated in 7.0.0. Use [[getClientSecret()]] instead.
     */
    public function getApiSecretKey(bool $parse = true): string
    {
        Craft::$app->getDeprecator()->log(__METHOD__, '`getApiSecretKey()` method has been deprecated. Use `getClientSecret()` instead.');
        return $this->getClientSecret($parse);
    }

    /**
     * @param string $clientSecret
     * @return void
     * @since 7.0.0
     */
    public function setClientSecret(string $clientSecret): void
    {
        $this->_clientSecret = $clientSecret;
    }

    /**
     * @param bool $parse
     * @return string
     * @since 7.0.0
     */
    public function getClientSecret(bool $parse = true): string
    {
        return ($parse ? App::parseEnv($this->_clientSecret) : $this->_clientSecret) ?? '';
    }


    /**
     * @param string $hostName
     * @return void
     * @since 6.0.0
     */
    public function setHostName(string $hostName): void
    {
        $this->_hostName = $hostName;
    }

    /**
     * @param bool $parse
     * @return string
     * @since 6.0.0
     */
    public function getHostName(bool $parse = true): string
    {
        return ($parse ? App::parseEnv($this->_hostName) : $this->_hostName) ?? '';
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
        $parsedValue = App::parseEnv($this->_contextualPricingCountries) ?? '';

        return ($parse ? StringHelper::toUpperCase($parsedValue) : $this->_contextualPricingCountries) ?? '';
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
        $url = UrlHelper::actionUrl('shopify/webhook/handle');
        $webhookBaseUrl = App::env('SHOPIFY_WEBHOOK_BASE_URL');

        if ($webhookBaseUrl) {
            $url = StringHelper::replaceFirst($url, rtrim(UrlHelper::baseUrl(), '/'), rtrim($webhookBaseUrl, '/'));
        }

        return $url;
    }

    /**
     * @return string
     * @since 7.0.0
     */
    public function getAuthUrl(): string
    {
        // Trim CP trigger if it's present.
        $authPath = $this->getAuthPath();
        if ($cpTrigger = Craft::$app->getConfig()->getGeneral()->cpTrigger) {
            $authPath = StringHelper::removeLeft($authPath, $cpTrigger . '/');
        }

        return UrlHelper::cpUrl($authPath);
    }

    /**
     * @return string
     * @since 7.0.0
     */
    public function getAuthPath(): string
    {
        return UrlHelper::prependCpTrigger('shopify/auth');
    }
}
