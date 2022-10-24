<?php

namespace craft\shopify\services;

use Craft;
use craft\base\Component;
use craft\helpers\App;
use craft\log\MonologTarget;
use craft\shopify\Plugin;
use Shopify\Auth\FileSessionStorage;
use Shopify\Auth\Session;
use Shopify\Clients\Rest;
use Shopify\Context;

/**
 * Shopify API service.
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 3.0
 *
 *
 * @property-read void $products
 */
class Api extends Component
{
    /**
     * @var string
     */
    public const SHOPIFY_API_VERSION = '2022-10';

    /**
     * @var Session|null
     */
    private ?Session $_session = null;

    /**
     * @return array
     */
    public function getAllProducts(): array
    {
        $session = $this->getSession();
        $client = new Rest($session->getShop(), $session->getAccessToken());
        $response = $client->get('products');
        $products = [];
        $this->_getProductsFromResponse($response, $products);

        return $products;
    }

    /**
     * Loops through all pages of the response to get all products
     *
     * @param $response
     * @param $products
     */
    private function _getProductsFromResponse($response, &$products)
    {
        $session = $this->getSession();
        $client = new Rest($session->getShop(), $session->getAccessToken());
        $products = array_merge($products, $response->getDecodedBody()['products'] ?? []);
        $pageInfo = $response->getPageInfo();
        if ($pageInfo && $pageInfo->hasNextPage()) {
            $response = $client->get('products', [], $pageInfo->getNextPageQuery());
            $this->_getProductsFromResponse($response, $products);
        }
    }

    /**
     * @return array
     */
    public function getProductByShopifyId($id): array
    {
        $session = $this->getSession();
        $client = new Rest($session->getShop(), $session->getAccessToken());
        $response = $client->get('product/' . $id);

        return $response->getDecodedBody()['product'];
    }

    /**
     * @return Session|null
     * @throws \Shopify\Exception\MissingArgumentException
     * @throws \yii\base\Exception
     */
    public function getSession(): ?Session
    {
        $pluginSettings = Plugin::getInstance()->getSettings();

        if (!$pluginSettings->apiKey || !$pluginSettings->accessToken || !$pluginSettings->hostName || !$pluginSettings->apiSecretKey) {
            Craft::error('Can not access Shopify API. Shopify API credentials are not configured.', __METHOD__);
            return null;
        }

        if ($this->_session === null) {
            /** @var MonologTarget $webLogTarget */
            $webLogTarget = Craft::$app->getLog()->targets['web'];
            Context::initialize(
                apiKey: App::parseEnv($pluginSettings->apiKey),
                apiSecretKey: App::parseEnv($pluginSettings->apiSecretKey),
                scopes: ['write_products', 'read_products'],
                hostName: !Craft::$app->request->isConsoleRequest ? Craft::$app->getRequest()->getHostName() : 'localhost',
                sessionStorage: new FileSessionStorage(Craft::$app->getPath()->getStoragePath() . DIRECTORY_SEPARATOR . 'shopify_api_sessions'),
                apiVersion: self::SHOPIFY_API_VERSION,
                isEmbeddedApp: false,
                logger: $webLogTarget->getLogger()
            );

            $hostName = App::parseEnv($pluginSettings->hostName);
            $accessToken = App::parseEnv($pluginSettings->accessToken);

            $this->_session = new Session(
                id: 'NA',
                shop: $hostName,
                isOnline: false,
                state: 'NA'
            );
            $this->_session->setAccessToken($accessToken); // this is the most important part of the authentication
        }

        return $this->_session;
    }
}
