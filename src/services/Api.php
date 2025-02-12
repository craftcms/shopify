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
use craft\helpers\ArrayHelper;
use craft\log\MonologTarget;
use craft\shopify\Plugin;
use GraphQL\InlineFragment;
use GraphQL\Mutation;
use GraphQL\QueryBuilder\QueryBuilder;
use GraphQL\Variable;
use GuzzleHttp\Client;
use Illuminate\Support\Collection;
use MaxGraphQL\Types\Query;
use Psr\Http\Client\ClientInterface;
use Shopify\ApiVersion;
use Shopify\Auth\FileSessionStorage;
use Shopify\Auth\Session;
use Shopify\Clients\Graphql;
use Shopify\Clients\HttpClientFactory;
use Shopify\Clients\Rest;
use Shopify\Context;
use Shopify\Exception\MissingArgumentException;
use Shopify\Rest\Admin2023_10\Metafield as ShopifyMetafield;
use Shopify\Rest\Admin2023_10\Product as ShopifyProduct;
use Shopify\Rest\Admin2023_10\Variant as ShopifyVariant;
use Shopify\Rest\Admin2024_10\Metafield as ShopifyMetafield2410;
use Shopify\Rest\Admin2024_10\Product as ShopifyProduct2410;
use Shopify\Rest\Admin2024_10\Variant as ShopifyVariant2410;
use Shopify\Webhooks\Topics;

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
     * @deprecated in 5.3.0. Use `Settings::getApiVersion()` instead.
     */
    public const SHOPIFY_API_VERSION = '2023-10';

    /**
     * @var string[]
     * @since 6.0.0
     */
    public const WEBHOOK_TOPICS = [
        Topics::PRODUCTS_CREATE,
        Topics::PRODUCTS_UPDATE,
        Topics::PRODUCTS_DELETE,
        Topics::INVENTORY_LEVELS_UPDATE,
        Topics::BULK_OPERATIONS_FINISH,
    ];

    /**
     * @var Session|null
     */
    private ?Session $_session = null;

    /**
     * @var Graphql|null
     */
    private ?Graphql $_client = null;

    /**
     * @return array
     * @since 5.3.0
     */
    public function getSupportedApiVersions(): array
    {
        return [
            ApiVersion::OCTOBER_2024,
            ApiVersion::OCTOBER_2023,
        ];
    }

    /**
     * @param string|null $id
     * @return \GraphQL\Query
     * @since 6.0.0
     */
    public function getProductGql(?string $id = null): \GraphQL\Query
    {
        $fields = collect([
            'edges' => [
                'node' => [
                    'descriptionHtml',
                    'createdAt',
                    'handle',
                    'id',
                    'media' => [
                        'edges' => [
                            'node' => [
                                'mediaContentType',
                                'id',
                                '... on MediaImage' => [
                                    'createdAt',
                                    'updatedAt',
                                    'image' => [
                                        'altText',
                                        'height',
                                        'width',
                                        'url',
                                    ],
                                ],
                            ],
                        ],
                    ],
                    'options' => [
                        'id',
                        'name',
                        'position',
                        'values',
                    ],
                    'productType',
                    'publishedAt',
                    'status',
                    'tags',
                    'templateSuffix',
                    'title',
                    'totalInventory',
                    'updatedAt',
                    'variants' => [
                        'edges' => [
                            'node' => [
                                'id',
                                'barcode',
                                'compareAtPrice',
                                'createdAt',
                                'price',
                                'sku',
                                'taxable',
                                'updatedAt',
                                'inventoryItem' => [
                                    'id',
                                ],
                                'inventoryQuantity',
                            ],
                        ],
                    ],
                    'vendor',
                ],
            ]
        ]);

        $builder = (new QueryBuilder('products'));

        if ($id) {
            // Strip Shopify prefix if it exists
            $id = str_replace('gid://shopify/Product/', '', $id);

            $builder->setArgument('query', sprintf('id:%s', $id));
        }

        foreach ($fields as $key => $value) {
            $this->_getBuilderValue($key, $value, $builder);
        }

        return $builder->getQuery();
    }

    /**
     * @param $key
     * @param $value
     * @param QueryBuilder $builderQuery
     * @return void
     */
    private function _getBuilderValue($key, $value, QueryBuilder $builderQuery)
    {
        if (is_array($value)) {
            $subQueryBuilder = (new QueryBuilder($key));
            foreach ($value as $k => $v) {
                $this->_getBuilderValue($k, $v, $subQueryBuilder);
            }

            $value = $subQueryBuilder->getQuery();
        }

        $builderQuery->selectField($value);
    }

    /**
     * Run a Shopify GraphQL query.
     *
     * @param \GraphQL\Query $query
     * @param array|null $variables
     * @return mixed
     */
    public function query(\GraphQL\Query $query, ?array $variables = null): mixed
    {
        $data = ['query' => (string)$query];
        if ($variables) {
            $data['variables'] = $variables;
        }

        try {
            $response = $this->getClient()->query($data);
            $body = $response->getDecodedBody();

            if (!isset($body['data'])) {
                throw new \Exception('No data returned from GraphQL query.');
            }

            $data = $body['data'];
            $data = ArrayHelper::firstValue($data);
            if (!empty($data['userErrors'])) {
                throw new \Exception($data['userErrors'][0]['message']);
            }

            return $data;
        } catch (\Exception $e) {
            Craft::error('Could not run GraphQL query: ' . $e->getMessage(), __METHOD__);

            return false;
        }
    }

    /**
     * Iteratively retrieves a paginated collection of API resources.
     *
     * @param Query $query
     * @return Collection
     */
    public function getAll(\GraphQL\Query $query, ?array $variables = null): Collection
    {
        $return = [];
        $hasNextPage = true;

        do {
            $data = ['query' => $query->__toString()];
            if ($variables) {
                $data['variables'] = $variables;
            }

            $response = $this->getClient()->query($data);
            $body = $response->getDecodedBody();

            if (!$body || !isset($body['data'])) {
                if (isset($body['errors'])) {
                    throw new \Exception($body['errors'][0]['message']);
                }

                $hasNextPage = false;
                continue;
            }

            $data = $body['data'];
            $data = ArrayHelper::firstValue($data);

            if (in_array('edges', array_keys($data))) {
                $data = $data['edges'];
            } elseif (in_array('nodes', array_keys($data))) {
                $data = $data['nodes'];
            }

            $return = array_merge($return, $data);

            if (!isset($data['pageInfo']) || !isset($data['pageInfo']['hasNextPage'])) {
                $hasNextPage = false;
                continue;
            }

            $hasNextPage = $data['pageInfo']['hasNextPage'];
            if ($hasNextPage) {
                $arguments = $query->getArguments();
                $arguments['after'] = $data['pageInfo']['endCursor'];
                $query->addArguments($arguments);
            }
        } while ($hasNextPage);

        return collect($return);
    }

    /**
     * Retrieve a single product by its Shopify ID.
     *
     * @return ShopifyProduct|ShopifyProduct2410
     */
    public function getProductByShopifyId($id): ShopifyProduct|ShopifyProduct2410
    {
        return $this->getProductClass()::find($this->getSession(), $id);
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

        if (isset($variant['variants'])) {
            return $variant['variants'][0]['product_id'];
        }

        return null;
    }

    /**
     * Retrieves "metafields" for the provided Shopify product ID.
     *
     * @param int $id Shopify Product ID
     * @return ShopifyMetafield[]|ShopifyMetafield2410[]
     */
    public function getMetafieldsByProductId(int $id): array
    {
        if (!Plugin::getInstance()->getSettings()->syncProductMetafields) {
            return [];
        }

        return $this->getMetafieldsByIdAndOwnerResource($id, 'products');
    }

    /**
     * @param int $id
     * @return ShopifyMetafield[]|ShopifyMetafield2410[]
     * @since 4.1.0
     */
    public function getMetafieldsByVariantId(int $id): array
    {
        if (!Plugin::getInstance()->getSettings()->syncVariantMetafields) {
            return [];
        }

        return $this->getMetafieldsByIdAndOwnerResource($id, 'variants');
    }

    /**
     * @param int $id
     * @param string $ownerResource
     * @return ShopifyMetafield[]|ShopifyMetafield2410[]
     * @since 4.1.0
     */
    public function getMetafieldsByIdAndOwnerResource(int $id, string $ownerResource): array
    {
        /** @var array $metafields */
        $metafields = $this->get("{$ownerResource}/{$id}/metafields", [
            'metafield' => [
                'owner_id' => $id,
                'owner_resource' => $ownerResource,
            ],
        ]);

        if (empty($metafields) || !isset($metafields['metafields'])) {
            return [];
        }

        $return = [];

        foreach ($metafields['metafields'] as $metafield) {
            $metafieldClass = $this->getMetaFieldClass();
            $return[] = new $metafieldClass($this->getSession(), $metafield);
        }

        return $return;
    }

    /**
     * Retrieves "variants" for the provided Shopify product ID.
     *
     * @param int $id Shopify Product ID
     */
    public function getVariantsByProductId(int $id): array
    {
        $resources = [];
        $params = ['limit' => 250];

        do {
            $resources = array_merge($resources, $this->getVariantClass()::all(
                $this->getSession(),
                ['product_id' => $id],
                $this->getVariantClass()::$NEXT_PAGE_QUERY ?: $params,
            ));
        } while ($this->getVariantClass()::$NEXT_PAGE_QUERY);

        $variants = [];
        foreach ($resources as $resource) {
            $variants[] = $resource->toArray();
        }

        return $variants;
    }

    /**
     * Shortcut for retrieving arbitrary API resources. A plain (parsed) response body is returned, so it’s the caller’s responsibility for unpacking it properly.
     *
     * @see Rest::get();
     */
    public function get($path, array $query = [])
    {
        $response = $this->getClient()->get($path, [], $query, 5);

        return $response->getDecodedBody();
    }

    /**
     * Returns or sets up a Rest API client.
     *
     * @return Graphql
     * @throws MissingArgumentException
     */
    public function getClient(): Graphql
    {
        if ($this->_client === null) {
            $session = $this->getSession();
            $this->_client = new Graphql($session->getShop(), $session->getAccessToken());
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
                apiVersion: $pluginSettings->getApiVersion(),
                isEmbeddedApp: false,
                logger: $webLogTarget->getLogger(),
            );

            Context::$HTTP_CLIENT_FACTORY = new class() extends HttpClientFactory {
                public function client(): ClientInterface
                {
                    // This is the default client, but we need to add the header for presentment prices
                    return new Client(['headers' => ['X-Shopify-Api-Features' => 'include-presentment-prices']]);
                }
            };

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

    /**
     * @return Collection
     * @throws \Exception
     * @since 6.0.0
     */
    public function getWebhooks(): Collection
    {
        $query = (new \GraphQL\Query('webhookSubscriptions'))
            ->setArguments(['first' => 100])
            ->setSelectionSet([
                (new \GraphQL\Query('nodes'))
                    ->setSelectionSet([
                        'id',
                        'topic',
                        (new \GraphQL\Query('endpoint'))
                            ->setSelectionSet([
                                (new InlineFragment('WebhookHttpEndpoint'))
                                    ->setSelectionSet([
                                        'callbackUrl'
                                    ]),
                            ]),
                    ])
            ]);

        return $this->getAll($query);
    }

    /**
     * @param string $id
     * @param string|null $error
     * @return bool
     * @throws MissingArgumentException
     * @since 6.0.0
     */
    public function deleteWebhookById(string $id, ?string &$error = null): bool
    {
        if ($this->getSession() === null) {
            $error = Craft::t('shopify', 'No Shopify session available.');
            return false;
        }

        $mutation = (new Mutation('webhookSubscriptionDelete'))
            ->setOperationName('webhookSubscriptionDelete')
            ->setVariables([
                new Variable('id', 'ID!'),
            ])
            ->setArguments([
                'id' => '$id',
            ])
            ->setSelectionSet([
                (new \GraphQL\Query('userErrors'))
                    ->setSelectionSet([
                        'field',
                        'message',
                    ]),
                'deletedWebhookSubscriptionId',
            ]);

        try {
            Plugin::getInstance()->getApi()->getClient()->query([
                'query' => (string)$mutation,
                'variables' => [
                    'id' => $id,
                ],
            ]);

            return true;
        } catch (\Exception $e) {
            Craft::error('Could not delete webhook with Shopify API: ' . $e->getMessage(), __METHOD__);

            $error = Craft::t('shopify', 'Webhook could not be deleted');
            return false;
        }
    }

    /**
     * @return string
     * @since 5.3.0
     * @phpstan-return class-string<ShopifyProduct|ShopifyProduct2410>
     */
    public function getProductClass(): string
    {
        return $this->_apiNamespace() . '\Product';
    }

    /**
     * @return string
     * @since 5.3.0
     * @phpstan-return class-string<ShopifyVariant|ShopifyVariant2410>
     */
    public function getVariantClass(): string
    {
        return $this->_apiNamespace() . '\Variant';
    }

    /**
     * @return string
     * @since 5.3.0
     * @phpstan-return class-string<ShopifyMetafield|ShopifyMetafield2410>
     */
    public function getMetaFieldClass(): string
    {
        return $this->_apiNamespace() . '\Metafield';
    }

    /**
     * @return string
     */
    private function _apiNamespace(): string
    {
        return 'Shopify\Rest\Admin' . str_replace('-', '_', Plugin::getInstance()->getSettings()->getApiVersion());
    }
}
