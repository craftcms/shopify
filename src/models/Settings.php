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
    private string $_apiKey = '';
    private string $_apiSecretKey = '';
    private string $_accessToken = '';
    private string $_hostName = '';
    public string $uriFormat = '';
    public string $template = '';
    private mixed $_productFieldLayout;

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
     */
    public bool $syncProductMetafields = true;

    /**
     * Whether variant metafields should be included when syncing products. This adds an extra API request per variant.
     *
     * @var bool
     * @since 4.1.0
     */
    public bool $syncVariantMetafields = false;

    public function rules(): array
    {
        return [
            [['apiSecretKey', 'apiKey', 'accessToken', 'hostName', 'apiVersion'], 'required'],
            [['apiVersion'], 'in', 'range' => Plugin::getInstance()->getApi()->getSupportedApiVersions()],
        ];
    }

    public function attributes()
    {
        $names = parent::attributes();
        $names[] = 'apiVersion';
        $names[] = 'apiKey';
        $names[] = 'apiSecretKey';
        $names[] = 'accessToken';
        $names[] = 'hostName';
        $names[] = 'uriFormat';
        $names[] = 'template';

        return $names;
    }

    public function fields(): array
    {
        return [
            'apiVersion' => fn() => $this->getApiVersion(false),
            'apiKey' => fn() => $this->getApiKey(false),
            'apiSecretKey' => fn() => $this->getApiSecretKey(false),
            'accessToken' => fn() => $this->getAccessToken(false),
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
            'apiKey' => Craft::t('shopify', 'Shopify API Key'),
            'apiSecretKey' => Craft::t('shopify', 'Shopify API Secret Key'),
            'apiVersion' => Craft::t('shopify', 'Shopify API Version'),
            'accessToken' => Craft::t('shopify', 'Shopify Access Token'),
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
     * @param string apiKey
     * @return void
     * @since 6.0.0
     */
    public function setApiKey(string $apiKey): void
    {
        $this->_apiKey = $apiKey;
    }

    /**
     * @param bool $parse
     * @return string
     * @since 6.0.0
     */
    public function getApiKey(bool $parse = true): string
    {
        return $parse ? App::parseEnv($this->_apiKey) : $this->_apiKey;
    }

    /**
     * @param string $apiSecretKey
     * @return void
     * @since 6.0.0
     */
    public function setApiSecretKey(string $apiSecretKey): void
    {
        $this->_apiSecretKey = $apiSecretKey;
    }

    /**
     * @param bool $parse
     * @return string
     * @since 6.0.0
     */
    public function getApiSecretKey(bool $parse = true): string
    {
        return $parse ? App::parseEnv($this->_apiSecretKey) : $this->_apiSecretKey;
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
        return $parse ? App::parseEnv($this->_hostName) : $this->_hostName;
    }

    /**
     * @param string $accessToken
     * @return void
     * @since 6.0.0
     */
    public function setAccessToken(string $accessToken): void
    {
        $this->_accessToken = $accessToken;
    }

    /**
     * @param bool $parse
     * @return string
     * @since 6.0.0
     */
    public function getAccessToken(bool $parse = true): string
    {
        return $parse ? App::parseEnv($this->_accessToken) : $this->_accessToken;
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
