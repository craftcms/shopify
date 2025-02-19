<?php

namespace craft\shopify\services;

use craft\base\Component;
use craft\helpers\App;
use craft\helpers\UrlHelper;
use craft\shopify\Plugin;
use yii\base\InvalidConfigException;

/**
 * Shopify Store service.
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 3.0
 */
class Store extends Component
{
    /**
     * @var array|null
     */
    private ?array $_shop = null;

    /**
     * Creates a URL to the external Shopify store
     *
     * @param string $path
     * @param array $params
     * @throws InvalidConfigException when no hostname is set up.
     * @return string
     */
    public function getUrl(string $path = '', array $params = []): string
    {
        $settings = Plugin::getInstance()->getSettings();
        $host = $settings->getHostName(true);

        if (!$host) {
            throw new InvalidConfigException('Shopify URLs cannot be generated without a hostname configured.');
        }

        return UrlHelper::url("https://{$host}/{$path}", $params);
    }

    /**
     * @return string
     * @throws InvalidConfigException
     */
    public function getCurrency(): string
    {
        return $this->getShopSettings()['currencyCode'];
    }

    /**
     * @return array|null
     * @throws InvalidConfigException
     */
    public function getShopSettings(): ?array
    {
        if ($this->_shop !== null) {
            return $this->_shop;
        }

        $this->_shop = Plugin::getInstance()->getApi()->getShop();

        return $this->_shop;
    }
}
