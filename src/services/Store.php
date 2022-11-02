<?php

namespace craft\shopify\services;

use Craft;
use craft\base\Component;
use craft\helpers\App;
use craft\helpers\UrlHelper;
use craft\shopify\elements\Product;
use craft\shopify\models\Settings;
use craft\shopify\Plugin;
use craft\shopify\records\ProductData as ProductDataRecord;
use yii\helpers\Url;

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
     * Creates a URL to the external shopify store
     *
     * @param string $path
     * @param array|string|null $params
     * @param string|null $scheme
     * @return string
     */
    public function getUrl(string $path = '', array|string|null $params = null, ?string $scheme = 'https'): string
    {
        $settings = Plugin::getInstance()->getSettings();
        if ($settings->hostName) {
            $query = UrlHelper::buildQuery($params ?? []);
            $query = $query ? "?$query" : '';
            $path = $path ? '/' . $path : '';
            $scheme = ($scheme ?: 'https') . '://';
            $base = App::parseEnv($settings->hostName);
            return $scheme . $base . $path . $query;
        }

        return '';
    }
}
