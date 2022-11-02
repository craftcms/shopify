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
}
