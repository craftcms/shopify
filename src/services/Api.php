<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\shopify\services;

use Craft;
use craft\base\Component;
use craft\helpers\ArrayHelper;
use craft\helpers\Json;
use craft\log\MonologTarget;
use craft\shopify\Plugin;
use craft\shopify\records\AccessToken;
use craft\shopify\records\ShopifyData;
use GraphQL\Mutation;
use GraphQL\Query;
use GraphQL\QueryBuilder\QueryBuilder;
use GraphQL\Variable;
use GuzzleHttp\Client;
use Illuminate\Support\Collection;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Shopify\ApiVersion;
use Shopify\Auth\FileSessionStorage;
use Shopify\Auth\OAuth;
use Shopify\Auth\Session;
use Shopify\Clients\Graphql;
use Shopify\Clients\Http;
use Shopify\Clients\HttpClientFactory;
use Shopify\Clients\Rest;
use Shopify\Context;
use Shopify\Exception\MissingArgumentException;
use Shopify\Exception\UninitializedContextException;
use Shopify\Webhooks\Topics;
use yii\base\InvalidConfigException;

/**
 * Shopify API service.
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 3.0
 *
 * @property-read void $products
 */
class Api extends Component
{
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
        Topics::SHOP_UPDATE,
    ];

    /**
     * @since 7.0.0
     */
    public const API_ACCESS_TOKEN_ENV_VAR = 'SHOPIFY_API_ACCESS_TOKEN';

    /**
     * @var Session|null
     */
    private ?Session $_session = null;

    /**
     * @var Graphql|null
     */
    private ?Graphql $_gqlClient = null;

    /**
     * @return array
     * @since 5.3.0
     */
    public function getSupportedApiVersions(): array
    {
        return [
            ApiVersion::OCTOBER_2025,
        ];
    }

    /**
     * @return Query
     * @since 6.0.0
     */
    public function getShopGql(): Query
    {
        $fields = [
            'id',
            'billingAddress' => [
                'address1',
                'address2',
                'city',
                'company',
                'country',
                'countryCodeV2',
                'formatted',
                'formattedArea',
                'id',
                'latitude',
                'longitude',
                'phone',
                'province',
                'provinceCode',
                'zip',
            ],
            'contactEmail',
            'createdAt',
            'currencyCode',
            'description',
            'email',
            'ianaTimezone',
            'marketingSmsConsentEnabledAtCheckout',
            'myshopifyDomain',
            'name',
            'orderNumberFormatPrefix',
            'orderNumberFormatSuffix',
            'taxesIncluded',
            'taxShipping',
            'timezoneAbbreviation',
            'updatedAt',
            'url',
            'weightUnit',
        ];

        return $this->createQuery('shop', $fields);
    }

    /**
     * @param bool $update
     * @return array|null
     * @since 6.0.0
     */
    public function getShop(bool $update = false): ?array
    {
        $shop = null;

        // Check if the data is synced into the DB
        $shopRecord = ShopifyData::findOne(['type' => 'Shop']);
        if ($shopRecord && !$update) {
            return Json::decodeIfJson($shopRecord->data);
        }

        // Sync the data from the API
        try {
            $response = $this->query($this->getShopGql());

            if (empty($response)) {
                throw new \Exception('Shop data not found in the response.');
            }

            if (!$shopRecord) {
                $shopRecord = new ShopifyData();
            }

            $shopRecord->shopifyId = $response['id'];
            $shopRecord->type = 'Shop';
            $shopRecord->data = $response;

            if (!$shopRecord->save()) {
                throw new \Exception('Failed to save shop data: ' . $shopRecord->getErrors()[0]);
            }

            $shop = $shopRecord->data;
        } catch (\Exception $e) {
            Craft::error('Failed to sync Shopify shop data: ' . $e->getMessage(), __METHOD__);
        }

        return $shop;
    }

    /**
     * @param string|null $id
     * @return Query
     * @since 6.0.0
     */
    public function getProductGql(?string $id = null): Query
    {
        $contextualPricingCountries = Plugin::getInstance()->getSettings()->getContextualPricingCountries();
        $contextualPricing = [];

        if ($contextualPricingCountries) {
            $contextualPricingCountries = explode(',', $contextualPricingCountries);
            foreach ($contextualPricingCountries as $country) {
                // Keys cannot contain whitespace:
                $key = trim($country);

                // Empty key, or not the right length?
                if (!$key || strlen($key) !== 2) {
                    continue;
                }

                // We request prices using the proper nested GQL params, but alias it to a key like `ukContextualPricing`:
                $contextualPricing[strtolower($key) . 'ContextualPricing:contextualPricing(context:{country:' . $key . '})'] = [
                    'price' => [
                        'amount',
                        'currencyCode',
                    ],
                    'compareAtPrice' => [
                        'amount',
                        'currencyCode',
                    ],
                ];
            }
        }

        $fields = [
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
                                'alt',
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
                    'metafields' => [
                        'edges' => [
                            'node' => [
                                'id',
                                'key',
                                'value',
                            ],
                        ],
                    ],
                    'options' => [
                        'id',
                        'name',
                        'position',
                        'values',
                        'optionValues' => [
                            'id',
                            'name',
                            'hasVariants',
                        ],
                    ],
                    'productType',
                    'publishedAt',
                    'publishedOnCurrentPublication',
                    'status',
                    'tags',
                    'templateSuffix',
                    'title',
                    'totalInventory',
                    'updatedAt',
                    'variants' => [
                        'edges' => [
                            'node' => array_merge(
                                $contextualPricing,
                                [
                                'id',
                                'barcode',
                                'compareAtPrice',
                                'createdAt',
                                'displayName',
                                'price',
                                'sku',
                                'taxable',
                                'title',
                                'updatedAt',
                                'position',
                                'image' => [
                                    'altText',
                                    'height',
                                    'id',
                                    'url',
                                    'width',
                                    'originalSrc',
                                    'src',
                                    'transformedSrc',
                                ],
                                'inventoryItem' => [
                                    'id',
                                    'countryCodeOfOrigin',
                                    'createdAt',
                                    'updatedAt',
                                    'sku',
                                    'tracked',
                                    'unitCost' => [
                                        'amount',
                                        'currencyCode',
                                    ],
                                ],
                                'inventoryPolicy',
                                'inventoryQuantity',
                                'metafields' => [
                                    'edges' => [
                                        'node' => [
                                            'id',
                                            'key',
                                            'value',
                                        ],
                                    ],
                                ],
                                'product' => [
                                    'id',
                                ],
                                'selectedOptions' => [
                                    'name',
                                    'value',
                                ],
                            ]),
                        ],
                    ],
                    'vendor',
                ],
            ],
        ];

        return $this->createQuery('products', $fields, function(QueryBuilder $builder) use ($id) {
            if ($id) {
                // Strip Shopify prefix if it exists
                $id = str_replace('gid://shopify/Product/', '', $id);

                $builder->setArgument('query', sprintf('id:%s', $id));
            }
        });
    }

    /**
     * @param $key
     * @param $value
     * @param QueryBuilder $builderQuery
     * @return void
     */
    private function _prepQueryBuilder($key, $value, QueryBuilder $builderQuery): void
    {
        if (is_array($value)) {
            $subQueryBuilder = (new QueryBuilder($key));
            foreach ($value as $k => $v) {
                $this->_prepQueryBuilder($k, $v, $subQueryBuilder);
            }

            $value = $subQueryBuilder->getQuery();
        }

        $builderQuery->selectField($value);
    }

    /**
     * @param string $name
     * @param array $fields
     * @param callable|null $beforeFields
     * @return Query
     * @since 6.0.0
     */
    public function createQuery(string $name, array $fields, callable $beforeFields = null): Query
    {
        $builder = new QueryBuilder($name);

        if ($beforeFields !== null) {
            $beforeFields($builder);
        }

        foreach ($fields as $key => $value) {
            $this->_prepQueryBuilder($key, $value, $builder);
        }

        return $builder->getQuery();
    }

    /**
     * Run a Shopify GraphQL query.
     *
     * @param Query|string $query
     * @param array|null $variables
     * @return mixed
     * @since 6.0.0
     */
    public function query(Query|string $query, ?array $variables = null): mixed
    {
        $data = ['query' => (string)$query];
        if ($variables) {
            $data['variables'] = $variables;
        }

        try {
            $response = $this->getGqlClient()->query($data);
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
     * Queries the data table for records of the specified type, optionally owned by one or more “parent” objects.
     *
     * @param string $type
     * @param string|false|null $parentId
     * @param bool $returnRecords
     * @return Collection
     * @since 6.0.0
     */
    public function getShopifyDataByType(string $type, array|string|null|false $parentId = null, bool $returnRecords = false): Collection
    {
        $criteria = ['type' => $type];
        if ($parentId !== null) {
            $criteria['parentId'] = $parentId ?: null;
        }

        $data = ShopifyData::find()
            ->where($criteria)
            ->orderBy(['id' => SORT_ASC])
            ->collect();

        // The caller can request the raw database rows, instead of just the `data` JSON column:
        if ($returnRecords) {
            return $data;
        }

        return $data->map(fn($record) => $record->data);
    }

    /**
     * Returns or sets up a Rest API client.
     *
     * @return Graphql
     * @throws MissingArgumentException
     * @since 6.0.0
     */
    public function getGqlClient(): Graphql
    {
        if ($this->_gqlClient === null) {
            $session = $this->getSession();

            if (!$session) {
                throw new InvalidConfigException('Unable to initialize API session. Check that your API credentials are correct and that you have authorized the app.');
            }

            $this->_gqlClient = new Graphql($session->getShop(), $session->getAccessToken());
        }

        return $this->_gqlClient;
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
            ($pluginSettings->getClientId(true)) &&
            ($pluginSettings->getClientSecret(true))
        ) {
            $this->initializeContext();

            $hostName = $pluginSettings->getHostName(true);
            $accessToken = $this->getAccessToken(shop: $hostName);

            // If there isn't an access token we can't create a session
            if ($accessToken) {
                $this->_session = new Session(
                    id: 'NA',
                    shop: $hostName,
                    isOnline: false,
                    state: 'NA'
                );

                $this->_session->setAccessToken($accessToken); // this is the most important part of the authentication
            }
        }

        return $this->_session;
    }

    /**
     * @return void
     * @throws MissingArgumentException
     * @throws \yii\base\Exception
     * @since 7.0.0
     */
    public function initializeContext(): void
    {
        $pluginSettings = Plugin::getInstance()->getSettings();
        /** @var MonologTarget $webLogTarget */
        $webLogTarget = Craft::$app->getLog()->targets['web'];

        Context::initialize(
            apiKey: $pluginSettings->getClientId(),
            apiSecretKey: $pluginSettings->getClientSecret(),
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
    }

    /**
     * @param string|null $code
     * @param string|null $shop
     * @return string|null
     * @throws ClientExceptionInterface
     * @throws UninitializedContextException
     * @throws \JsonException
     * @since 7.0.0
     */
    public function getAccessToken(?string $code = null, ?string $shop = null): ?string
    {
        // Try and retrieve the access token from the cache
        if ($accessToken = Plugin::getInstance()->getSettings()->getAccessToken()) {
            return $accessToken;
        }

        if (!$code || !$shop) {
            return null;
        }

        $client = new Http($shop);

        try {
            $response = $client->post(OAuth::ACCESS_TOKEN_POST_PATH, [
                'client_id' => Plugin::getInstance()->getSettings()->getClientId(true),
                'client_secret' => Plugin::getInstance()->getSettings()->getClientSecret(true),
                'code' => $code,
                'expiring' => 0,
            ]);

            $body = $response->getDecodedBody();

            if (!isset($body['access_token'])) {
                throw new \Exception('No access token returned from Shopify.');
            }

            $configService = Craft::$app->getConfig();
            $record = AccessToken::find()->one() ?? new AccessToken();

            $success = true;
            try {
                $configService->setDotEnvVar(self::API_ACCESS_TOKEN_ENV_VAR, $body['access_token']);
            } catch (\Throwable $e) {
                $success = false;
                Craft::error('Couldn\'t save the Shopify Access Token in the .env file. ' . $e->getMessage(), __METHOD__);
            }
            $record->accessToken = $success ? '$' . self::API_ACCESS_TOKEN_ENV_VAR : Craft::$app->getSecurity()->encryptByKey($body['access_token']);

            if (!$record->save()) {
                Craft::error('Couldn\'t save the Shopify Access Token in the database. ' . $record->getErrors()[0], __METHOD__);
            }

            return $body['access_token'];
        } catch (\Exception $e) {
            Craft::error('Could not get access token from Shopify: ' . $e->getMessage(), __METHOD__);
            throw $e;
        }
    }

    /**
     * @return Collection
     * @throws \Exception
     * @since 6.0.0
     */
    public function getWebhooks(): Collection
    {
        $query = $this->createQuery('webhookSubscriptions', [
            'nodes' => [
                'id',
                'topic',
                'endpoint' => [
                    '... on WebhookHttpEndpoint' => [
                        'callbackUrl',
                    ],
                ],
            ],
        ], function(QueryBuilder $builder) {
            $builder->setArgument('first', 100);
        });

        $response = $this->query($query);

        if (empty($response) || !isset($response['nodes'])) {
            return collect();
        }

        return collect($response['nodes']);
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
                (new Query('userErrors'))
                    ->setSelectionSet([
                        'field',
                        'message',
                    ]),
                'deletedWebhookSubscriptionId',
            ]);

        try {
            Plugin::getInstance()->getApi()->getGqlClient()->query([
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
}
