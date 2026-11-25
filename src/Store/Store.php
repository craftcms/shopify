<?php

declare(strict_types=1);

namespace CraftCms\Shopify\Store;

use CraftCms\Cms\Support\Url;
use CraftCms\Shopify\Api\Api;
use CraftCms\Shopify\Settings;
use Illuminate\Container\Attributes\Singleton;
use RuntimeException;

/**
 * Shopify Store service.
 */
#[Singleton]
class Store
{
    /**
     * @var array<string, mixed>|null
     */
    private ?array $shop = null;

    public function __construct(
        private readonly Settings $settings,
        private readonly Api $api,
    ) {
    }

    /**
     * Creates a URL to the external Shopify store.
     *
     * @param array<string, mixed> $params
     * @throws RuntimeException when no hostname is set up.
     */
    public function getUrl(string $path = '', array $params = []): string
    {
        $host = $this->settings->getHostName();

        if (!$host) {
            throw new RuntimeException('Shopify URLs cannot be generated without a hostname configured.');
        }

        return Url::url("https://{$host}/{$path}", $params);
    }

    public function getCurrency(): string
    {
        return $this->getShopSettings()['currencyCode'];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getShopSettings(): ?array
    {
        return $this->shop ??= $this->api->getShop();
    }
}
