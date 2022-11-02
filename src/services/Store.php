<?php

namespace craft\shopify\services;

use Craft;
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
 *
 *
 * @property-read void $products
 */
class Store extends Component
{
    /**
     * Creates a URL to the external Shopify store
     *
     * @param string $path
     * @param array|string|null $params
     * @param string|null $scheme
     * @throws InvalidConfigException when no hostname is set up.
     * @return string
     */
    public function getUrl(string $path = '', array|string|null $params = null, ?string $scheme = 'https://'): string
    {
        $settings = Plugin::getInstance()->getSettings();
        $host = App::parseEnv($settings->hostName);

        if (!$host) {
            throw new InvalidConfigException('Shopify URLs cannot be generated without a hostname configured.');
        }

        return UrlHelper::url("{$scheme}{$host}/{$path}", $params);
    }
}
