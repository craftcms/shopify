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
        $host = App::parseEnv($settings->hostName);

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
        return $this->getShopSettings()['currency'];
    }

    /**
     * @return array|string|null
     * @throws InvalidConfigException
     */
    public function getShopSettings()
    {
        $cacheKey = 'shopify-shop';
        $shop = Craft::$app->getCache()->get($cacheKey);
        if (!$shop) {
            $resource = Plugin::getInstance()->getApi()->get('shop');
            Craft::$app->getCache()->set($cacheKey, $resource['shop']);
            $shop = $resource['shop'];
        }

        return $shop;
    }
}
