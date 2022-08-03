<?php

namespace craft\shopify\services;

use Craft;
use craft\base\Component;
use craft\helpers\App;
use craft\shopify\Plugin;
use Shopify\Auth\FileSessionStorage;
use Shopify\Auth\OAuth;
use Shopify\Auth\Session;
use Shopify\Clients\Rest;
use Shopify\Context;
use Shopify\Rest\Admin2022_04\Product as ShopifyProduct;
use Shopify\Utils;
use yii\helpers\Url;

/**
 * Shopify API service.
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 1.0
 *
 *
 * @property-read void $products
 */
class Api extends Component
{
    public const SHOPIFY_API_VERSION = '2022-04';
    private ?Session $_session = null;

    /**
     * @return array
     */
    public function getAllProducts(): array
    {
        return collect(ShopifyProduct::all($this->getSession()))->map(function ($product) {
            return $product->toArray();
        })->all();
    }

    public function getSession(): ?Session
    {
        $pluginSettings = Plugin::getInstance()->getSettings();

        if (!$pluginSettings->apiKey || !$pluginSettings->accessToken || !$pluginSettings->hostName || !$pluginSettings->apiSecretKey) {
            Craft::error('Can not access Shopify API. Shopify API credentials are not configured.', __METHOD__);
            return null;
        }

        if ($this->_session === null) {

            Context::initialize(
                apiKey: App::parseEnv($pluginSettings->apiKey),
                apiSecretKey: App::parseEnv($pluginSettings->apiSecretKey),
                scopes: ['write_products', 'read_products'],
                hostName: Craft::$app->getRequest()->getHostName(),
                sessionStorage: new FileSessionStorage(Craft::$app->getPath()->getStoragePath() . DIRECTORY_SEPARATOR . 'shopify_api_sessions'),
                apiVersion: self::SHOPIFY_API_VERSION,
                isEmbeddedApp: false,
                logger: Craft::$app->getLog()->targets['web']->logger
            );

            $hostName = App::parseEnv($pluginSettings->hostName);
            $accessToken = App::parseEnv($pluginSettings->accessToken);

            $this->_session = new Session(
                id: 'NA',
                shop: $hostName,
                isOnline: false,
                state: 'NA'
            );
            $this->_session->setAccessToken($accessToken);
        }

        return $this->_session;
    }

}