<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\shopify\services;

use Craft;
use craft\base\Component;
use craft\helpers\App;
use craft\log\MonologTarget;
use craft\shopify\helpers\Api as ApiHelper;
use craft\shopify\Plugin;
use Shopify\Auth\FileSessionStorage;
use Shopify\Auth\Session;
use Shopify\Clients\Rest;
use Shopify\Context;
use Shopify\Rest\Admin2023_10\Metafield as ShopifyMetafield;
use Shopify\Rest\Admin2023_10\Product as ShopifyProduct;
use Shopify\Rest\Base as ShopifyBaseResource;

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
    public const SHOPIFY_API_VERSION = '2023-10';

    /**
     * @var Session|null
     */
    private ?Session $_session = null;

    /**
     * @var Rest|null
     */
    private ?Rest $_client = null;

    /**
     * Retrieve all a shop’s products.
     *
     * @return ShopifyProduct[]
     */
    public function getAllProducts(): array
    {
        /** @var ShopifyProduct[] $all */
        $all = $this->getAll(ShopifyProduct::class);

        return $all;
    }

    /**
     * Retrieve a single product by its Shopify ID.
     *
     * @return ShopifyProduct
     */
    public function getProductByShopifyId($id): ShopifyProduct
    {
        return ShopifyProduct::find($this->getSession(), $id);
    }

    /**
     * Retrieve a product ID by a variant's inventory item ID.
     *
     * @return ?int The product Shopify ID
     */
    public function getProductIdByInventoryItemId($id): ?int
    {
        $variant = Plugin::getInstance()->getApi()->get('variants', [
            'inventory_item_id' => $id,
        ]);

        if ($variant['variants']) {
            return $variant['variants'][0]['product_id'];
        }

        return null;
    }

    /**
     * Retrieves "metafields" for the provided Shopify product ID.
     *
     * @param int $id Shopify Product ID
     */
    public function getMetafieldsByProductId(int $id): array
    {
        return $this->getAll(ShopifyMetafield::class, [
            'metafield' => [
                'owner_id' => $id,
                'owner_resource' => 'product',
            ],
        ]);
    }

    /**
     * Retrieves "metafields" for the provided Shopify product ID.
     *
     * @param int $id Shopify Product ID
     */
    public function getVariantsByProductId(int $id): array
    {
        $variants = $this->get("products/{$id}/variants");

        return $variants['variants'];
    }

    /**
     * Shortcut for retrieving arbitrary API resources. A plain (parsed) response body is returned, so it’s the caller’s responsibility for unpacking it properly.
     *
     * @see Rest::get();
     */
    public function get($path, array $query = [])
    {
        $response = $this->getClient()->get($path, [], $query);

        return $response->getDecodedBody();
    }

    /**
     * Iteratively retrieves a paginated collection of API resources.
     *
     * @param string $type Stripe API resource class
     * @param array $params
     * @return ShopifyBaseResource[]
     */
    public function getAll(string $type, array $params = []): array
    {
        $resources = [];

        // Force maximum page size:
        $params['limit'] = 250;

        do {
            $resources = array_merge($resources, $type::all(
                $this->getSession(),
                [],
                $type::$NEXT_PAGE_QUERY ?: $params,
            ));
            ApiHelper::rateLimit(); // Avoid rate limiting
        } while ($type::$NEXT_PAGE_QUERY);

        return $resources;
    }

    /**
     * Returns or sets up a Rest API client.
     *
     * @return Rest
     */
    public function getClient(): Rest
    {
        if ($this->_client === null) {
            $session = $this->getSession();
            $this->_client = new Rest($session->getShop(), $session->getAccessToken());
        }

        return $this->_client;
    }

    /**
     * Returns or initializes a context + session.
     *
     * @return Session|null
     * @throws \Shopify\Exception\MissingArgumentException
     */
    public function getSession(): ?Session
    {
        $pluginSettings = Plugin::getInstance()->getSettings();

        if (
            $this->_session === null &&
            ($apiKey = App::parseEnv($pluginSettings->apiKey)) &&
            ($apiSecretKey = App::parseEnv($pluginSettings->apiSecretKey))
        ) {
            /** @var MonologTarget $webLogTarget */
            $webLogTarget = Craft::$app->getLog()->targets['web'];

            Context::initialize(
                apiKey: $apiKey,
                apiSecretKey: $apiSecretKey,
                scopes: ['write_products', 'read_products', 'read_inventory'],
                // This `hostName` is different from the `shop` value used when creating a Session!
                // Shopify wants a name for the host/environment that is initiating the connection.
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
