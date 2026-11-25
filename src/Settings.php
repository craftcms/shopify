<?php

declare(strict_types=1);

namespace CraftCms\Shopify;

use craft\shopify\elements\Product;
use craft\shopify\enums\ApiVersion;
use CraftCms\Cms\Cms;
use CraftCms\Cms\Cp\RequestedSite;
use CraftCms\Cms\FieldLayout\FieldLayout;
use CraftCms\Cms\Plugin\PluginSettings;
use CraftCms\Cms\Support\Arr;
use CraftCms\Cms\Support\Env;
use CraftCms\Cms\Support\Facades\Fields;
use CraftCms\Cms\Support\Str;
use CraftCms\Cms\Support\Url;
use CraftCms\Cms\Validation\Rules\EnvValueRule;
use CraftCms\Shopify\Api\Models\AccessToken;
use CraftCms\Shopify\Helpers\ShopifyHelper;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

use function CraftCms\Cms\action_url;
use function CraftCms\Cms\t;

/**
 * Shopify plugin settings.
 *
 * Settings that can reference environment variables keep their raw value in the public property, which is what
 * project config stores. Use the getters for the parsed value.
 */
class Settings extends PluginSettings
{
    public const array REQUIRED_SCOPES = ['read_inventory', 'read_product_listings', 'read_products'];

    public string $apiVersion = ApiVersion::January2026->value;

    public string $clientId = '';

    public string $clientSecret = '';

    public string $hostName = '';

    /** @var string[] */
    public array $additionalFeatures = [];

    public string $customScopes = '' {
        set(string $value) {
            // Env var references are kept as-is; plain-text values are normalized.
            if (!str_starts_with($value, '$')) {
                $value = implode(',', array_filter(array_map(
                    self::normalizeScope(...),
                    explode(',', $value),
                )));
            }

            $this->customScopes = $value;
        }
    }

    /**
     * @var string|null Comma-separated list of country codes to use for contextual pricing.
     */
    public ?string $contextualPricingCountries = null;

    public string $uriFormat = '';

    public string $template = '';

    private ?string $accessToken = null;

    private ?FieldLayout $productFieldLayout = null;

    #[\Override]
    public function getRules(): array
    {
        return array_merge(parent::getRules(), [
            'apiVersion' => [new EnvValueRule(['required', Rule::in(array_column(ApiVersion::cases(), 'value'))])],
            'clientId' => [new EnvValueRule(['required', 'string'])],
            'clientSecret' => [new EnvValueRule(['required', 'string'])],
            'hostName' => [new EnvValueRule(['required', 'string', function(string $attribute, mixed $value, \Closure $fail) {
                if (ShopifyHelper::sanitizeShopDomain($value) === null) {
                    $fail(t('The host name must be a valid Shopify store domain.', category: 'shopify'));
                }
            }])],
            'additionalFeatures' => ['array'],
            'additionalFeatures.*' => [Rule::in(array_keys($this->getAdditionalFeaturesOptions()))],
            'customScopes' => ['nullable', 'string'],
        ]);
    }

    /**
     * Ignores `accessToken`, which was a setting before Shopify 7 and can still be in project config or a config
     * file. The access token is stored in the `shopify_accesstokens` table.
     *
     * @param array<string, mixed> $values
     */
    #[\Override]
    public function setAttributes($values): void
    {
        parent::setAttributes(Arr::except($values, ['accessToken']));
    }

    #[\Override]
    public function attributeLabels(): array
    {
        return [
            'additionalFeatures' => t('Additional Features', category: 'shopify'),
            'apiVersion' => t('Shopify API Version', category: 'shopify'),
            'authUrl' => t('Shopify App Auth URL', category: 'shopify'),
            'clientId' => t('Shopify Client ID', category: 'shopify'),
            'clientSecret' => t('Shopify Client Secret Key', category: 'shopify'),
            'contextualPricingCountries' => t('Context Pricing Countries', category: 'shopify'),
            'customScopes' => t('Custom Scopes', category: 'shopify'),
            'hostName' => t('Shopify Host Name', category: 'shopify'),
            'scopes' => t('Scopes', category: 'shopify'),
            'template' => t('Product Template', category: 'shopify'),
            'uriFormat' => t('Product URI format', category: 'shopify'),
        ];
    }

    public function getApiVersion(bool $parse = true): string
    {
        return ($parse ? Env::parse($this->apiVersion) : $this->apiVersion) ?? '';
    }

    public function getClientId(bool $parse = true): string
    {
        return ($parse ? Env::parse($this->clientId) : $this->clientId) ?? '';
    }

    public function getClientSecret(bool $parse = true): string
    {
        return ($parse ? Env::parse($this->clientSecret) : $this->clientSecret) ?? '';
    }

    public function getHostName(bool $parse = true): string
    {
        return ($parse ? Env::parse($this->hostName) : $this->hostName) ?? '';
    }

    /**
     * @return string[]
     */
    public function getAdditionalFeatures(bool $asScopes = false): array
    {
        if (!$asScopes) {
            return $this->additionalFeatures;
        }

        $options = $this->getAdditionalFeaturesOptions();

        return array_values(array_filter(array_map(
            fn(string $feature) => $options[$feature]['scope'] ?? null,
            $this->additionalFeatures,
        )));
    }

    /**
     * @return array<string, array{label: string, value: string, scope: string}>
     */
    public function getAdditionalFeaturesOptions(): array
    {
        return [
            'productTranslations' => [
                'label' => t('Product Translations', category: 'shopify'),
                'value' => 'productTranslations',
                'scope' => 'read_locales',
            ],
        ];
    }

    public function getCustomScopes(bool $parse = true): string
    {
        return ($parse ? Env::parse($this->customScopes) : $this->customScopes) ?? '';
    }

    /**
     * @return ($asArray is true ? string[] : string)
     */
    public function getScopes(bool $asArray = false): array|string
    {
        $scopes = array_merge(self::REQUIRED_SCOPES, $this->getAdditionalFeatures(true));

        if ($customScopes = $this->getCustomScopes()) {
            $scopes = array_merge($scopes, array_filter(array_map(
                self::normalizeScope(...),
                explode(',', $customScopes),
            )));
        }

        $scopes = array_unique($scopes);
        asort($scopes);

        return $asArray ? $scopes : implode(',', $scopes);
    }

    /**
     * Lowercases and trims a scope, and returns an empty string if it contains characters outside `[a-z0-9_]`.
     */
    private static function normalizeScope(string $scope): string
    {
        $normalized = strtolower(trim($scope));

        return preg_match('/^[a-z0-9_]+$/', $normalized) ? $normalized : '';
    }

    public function setAccessToken(string $accessToken): void
    {
        $this->accessToken = $accessToken;
    }

    public function getAccessToken(bool $parse = true): string
    {
        if ($this->accessToken === null) {
            $record = AccessToken::query()->first();
            $accessToken = $record?->accessToken ?: '';

            // A stored token that isn't an env var reference is encrypted.
            if ($accessToken !== '' && !str_starts_with($accessToken, '$')) {
                try {
                    $accessToken = Str::decdec($accessToken);
                } catch (DecryptException) {
                    Log::warning('The stored Shopify access token couldn’t be decrypted, so Shopify needs to be reauthorized. Tokens encrypted by Craft 5 can’t be decrypted by Craft 6.');
                    $accessToken = '';
                }
            }

            $this->accessToken = $accessToken;
        }

        return ($parse ? Env::parse($this->accessToken) : $this->accessToken) ?? '';
    }

    public function getContextualPricingCountries(bool $parse = true): string
    {
        if (!$parse) {
            return $this->contextualPricingCountries ?? '';
        }

        return strtoupper(Env::parse($this->contextualPricingCountries) ?? '');
    }

    public function getProductFieldLayout(): FieldLayout
    {
        return $this->productFieldLayout ??= Fields::getLayoutByType(Product::class);
    }

    public function setProductFieldLayout(FieldLayout $fieldLayout): void
    {
        $this->productFieldLayout = $fieldLayout;
    }

    public function getWebhookUrl(): string
    {
        return $this->normalizePublicDevUrl(action_url('shopify/webhook/handle'));
    }

    public function getAuthUrl(): string
    {
        $authPath = Str::chopStart($this->getAuthPath(), Cms::config()->cpTrigger . '/');
        $url = Url::cpUrl($authPath);

        if ($requestedSite = app(RequestedSite::class)->get()?->handle) {
            $url = rtrim(str_replace("site=$requestedSite", '', $url), '?');
        }

        return $this->normalizePublicDevUrl($url);
    }

    public function getAuthPath(): string
    {
        return Url::prependCpTrigger('shopify/auth');
    }

    /**
     * Swaps the base URL for `SHOPIFY_PUBLIC_DEV_URL`, so Shopify can reach a local site through a tunnel.
     */
    private function normalizePublicDevUrl(string $url): string
    {
        if (!$publicDevUrl = Env::get('SHOPIFY_PUBLIC_DEV_URL')) {
            return $url;
        }

        $baseUrl = request()->isCpRequest() ? Url::baseCpUrl() : Url::baseSiteUrl();

        return Str::replaceFirst(rtrim($baseUrl, '/'), rtrim($publicDevUrl, '/'), $url);
    }
}
