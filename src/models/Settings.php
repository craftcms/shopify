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
use craft\helpers\Cp;
use craft\helpers\StringHelper;
use craft\helpers\UrlHelper;
use craft\shopify\elements\Product;
use craft\shopify\Plugin;
use craft\shopify\records\AccessToken;
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
    private string $_accessToken = '';

    private string $_hostName = '';
    private array $_additionalFeatures = [];
    private string $_customScopes = '';
    public string $uriFormat = '';
    public string $template = '';
    private mixed $_productFieldLayout;

    public const REQUIRED_SCOPES = ['read_inventory', 'read_product_listings', 'read_products'];

    /**
     * @var string|null Comma separated list of country codes to use for contextual pricing.
     */
    private ?string $_contextualPricingCountries = null;

    /**
     * @var string The Shopify API version to use.
     * @see setApiVersion()
     * @see getApiVersion()
     */
    private string $_apiVersion = ApiVersion::JANUARY_2026;

    public function rules(): array
    {
        return [
            [['clientSecret', 'clientId', 'hostName', 'apiVersion'], 'required'],
            [['apiVersion'], 'in', 'range' => Plugin::getInstance()->getApi()->getSupportedApiVersions()],
            [['additionalFeatures'], 'in', 'range' => array_keys($this->getAdditionalFeaturesOptions()), 'allowArray' => true],
            [['customScopes'], 'string', 'skipOnEmpty' => true],
            [['hostName'], function($attribute) {
                $hostName = $this->$attribute;

                if (Utils::sanitizeShopDomain($hostName) === null) {
                    $this->addError($attribute, Craft::t('shopify', 'The host name must be a valid Shopify store domain.'));
                }
            }, 'skipOnEmpty' => true],
        ];
    }

    public function attributes()
    {
        $names = parent::attributes();
        $names[] = 'additionalFeatures';
        $names[] = 'apiVersion';
        $names[] = 'clientId';
        $names[] = 'clientSecret';
        $names[] = 'contextualPricingCountries';
        $names[] = 'customScopes';
        $names[] = 'hostName';
        $names[] = 'uriFormat';
        $names[] = 'template';

        return $names;
    }

    public function fields(): array
    {
        return [
            'additionalFeatures' => fn() => $this->getAdditionalFeatures(),
            'apiVersion' => fn() => $this->getApiVersion(false),
            'clientId' => fn() => $this->getClientId(false),
            'clientSecret' => fn() => $this->getClientSecret(false),
            'contextualPricingCountries' => fn() => $this->getContextualPricingCountries(false),
            'customScopes' => fn() => $this->getCustomScopes(false),
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
            'additionalFeatures' => Craft::t('app', 'Additional Features'),
            'apiVersion' => Craft::t('shopify', 'Shopify API Version'),
            'authUrl' => Craft::t('shopify', 'Shopify App Auth URL'),
            'clientId' => Craft::t('shopify', 'Shopify Client ID'),
            'clientSecret' => Craft::t('shopify', 'Shopify Client Secret Key'),
            'contextualPricingCountries' => Craft::t('shopify', 'Context Pricing Countries'),
            'customScopes' => Craft::t('shopify', 'Custom Scopes'),
            'hostName' => Craft::t('shopify', 'Shopify Host Name'),
            'scopes' => Craft::t('shopify', 'Scopes'),
            'template' => Craft::t('shopify', 'Product Template'),
            'uriFormat' => Craft::t('shopify', 'Product URI format'),
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
     * @param array $additionalFeatures
     * @return void
     * @since 7.2.0
     */
    public function setAdditionalFeatures(array $additionalFeatures): void
    {
        $this->_additionalFeatures = $additionalFeatures;
    }

    /**
     * @return array
     * @since 7.2.0
     */
    public function getAdditionalFeatures(bool $asScopes = false): array
    {
        if ($asScopes && !empty($this->_additionalFeatures)) {
            $scopes = [];
            foreach ($this->_additionalFeatures as $additionalFeature) {
                $adFeat = $this->getAdditionalFeaturesOptions()[$additionalFeature] ?? null;
                if ($adFeat) {
                    $scopes[] = $adFeat['scope'];
                }
            }

            return $scopes;
        }

        return $this->_additionalFeatures;
    }

    /**
     * @return array<string, array{label: string, value: string, scope: string}>
     * @since 7.2.0
     */
    public function getAdditionalFeaturesOptions(): array
    {
        return [
            'productTranslations' => [
                'label' => Craft::t('shopify', 'Product Translations'),
                'value' => 'productTranslations',
                'scope' => 'read_locales',
            ],
        ];
    }


    /**
     * @param string $additionalScopes
     * @return void
     * @since 7.2.0
     */
    public function setCustomScopes(string $additionalScopes): void
    {
        // Preserve env var references as-is; normalize plain-text values
        if (!str_starts_with($additionalScopes, '$')) {
            $additionalScopes = implode(',', array_filter(array_map(
                fn($s) => preg_match('/^[a-z0-9_]+$/', $normalized = strtolower(trim($s))) ? $normalized : '',
                explode(',', $additionalScopes)
            )));
        }

        $this->_customScopes = $additionalScopes;
    }

    /**
     * @param bool $parse
     * @return string
     * @since 7.2.0
     */
    public function getCustomScopes(bool $parse = true): string
    {
        return ($parse ? App::parseEnv($this->_customScopes) : $this->_customScopes) ?? '';
    }

    /**
     * @param bool $asArray
     * @return array|string
     * @since 7.2.0
     */
    public function getScopes(bool $asArray = false): array|string
    {
        $scopes = array_merge(self::REQUIRED_SCOPES, $this->getAdditionalFeatures(true));

        $customScopes = $this->getCustomScopes();
        if ($customScopes) {
            $scopes = array_merge($scopes, array_filter(array_map(
                fn($s) => preg_match('/^[a-z0-9_]+$/', $normalized = strtolower(trim($s))) ? $normalized : '',
                explode(',', $customScopes)
            )));
        }

        $scopes = array_unique($scopes);
        asort($scopes);

        if ($asArray) {
            return $scopes;
        }

        return implode(',', $scopes);
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
        if (!$this->_accessToken) {
            /** @var AccessToken $accessTokenRecord */
            $accessTokenRecord = AccessToken::find()->one() ?? new AccessToken();
            if (!$accessTokenRecord->accessToken) {
                return '';
            }

            $accessToken = $accessTokenRecord->accessToken;

            // If an actual access token, and not an env var, has been stored we need to decrypt it
            if (!str_starts_with($accessToken, '$')) {
                $accessToken = StringHelper::decdec($accessToken);
            }

            $this->setAccessToken($accessToken);
        }

        return ($parse ? App::parseEnv($this->_accessToken) : $this->_accessToken) ?? '';
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

        return $this->_normalizePublicDevUrl($url);
    }

    /**
     * @param string $url
     * @return string
     * @throws \craft\errors\SiteNotFoundException
     * @throws \yii\base\Exception
     */
    private function _normalizePublicDevUrl(string $url): string
    {
        $publicDevUrl = App::env('SHOPIFY_PUBLIC_DEV_URL');
        if (!$publicDevUrl) {
            return $url;
        }

        return StringHelper::replaceFirst($url, rtrim(UrlHelper::baseUrl(), '/'), rtrim($publicDevUrl, '/'));
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

        $url = UrlHelper::cpUrl($authPath);
        $requestedSite = Cp::requestedSite()?->handle ?? null;

        if ($requestedSite && strpos($url, 'site=' . $requestedSite) > -1) {
            $url = str_replace("site={$requestedSite}", '', $url);
            $url = StringHelper::removeRight($url, '?');
        }

        return $this->_normalizePublicDevUrl($url);
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
