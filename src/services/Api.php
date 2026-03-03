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
use craft\shopify\events\DefineGqlFieldsEvent;
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
use Shopify\Context;
use Shopify\Exception\MissingArgumentException;
use Shopify\Exception\SessionNotFoundException;
use Shopify\Exception\ShopifyException;
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
     * @event DefineGqlFieldsEvent Triggered while building a GraphQL query for retrieving Product resources from Shopify.
     * @since 7.0.0
     */
    public const EVENT_DEFINE_PRODUCT_GQL_FIELDS = 'defineProductGqlFields';

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
                // Get the first error message from the record, if available:
                $errors = $shopRecord->getErrors();
                $firstError = array_shift($errors)[0] ?? ['Unknown error'];

                throw new \Exception('Failed to save shop data: ' . implode(', ', $firstError));
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

        $event = new DefineGqlFieldsEvent([
            'fields' => $fields,
        ]);
        $this->trigger(self::EVENT_DEFINE_PRODUCT_GQL_FIELDS, $event);

        return $this->createQuery('products', $event->fields, function(QueryBuilder $builder) use ($id) {
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
     * Run a GraphQL query against the Shopify API.
     *
     * If you need to control how a response is unpacked, use {@see getGqlClient()} directly.
     *
     * Under normal circumstances, the selected fields (including `userErrors`, when requested) are returned as an array.
     * A `false` return value indicates a low-level communication failure.
     *
     * All other issues should trigger a {@see ShopifyException}.
     *
     * @param Query|string $query
     * @param array|null $variables
     * @return mixed Typically an array with the same structure as the selection, or `null` for nonexistent nodes.
     * @throws ShopifyException when the response looks unusual (i.e. an `errors` key is present, or a `data` key was not returned)
     * @throws SessionNotFoundException if a session can’t be established
     * @since 6.0.0
     */
    public function query(Query|string $query, ?array $variables = null): mixed
    {
        // An invalid session will cause everything to fail:
        if ($this->getSession() === null) {
            throw new SessionNotFoundException(Craft::t('shopify', 'No Shopify session available. Please check your credentials and re-authorize the application, if necessary.'));
        }

        $payload = ['query' => (string)$query];

        if ($variables) {
            $payload['variables'] = $variables;
        }

        try {
            $response = $this->getGqlClient()->query($payload);
            $body = $response->getDecodedBody();

            if (array_key_exists('errors', $body)) {
                $message = $body['errors'];

                // https://shopify.dev/docs/api/admin-graphql/2025-10#status-and-error-codes
                // Some low-level errors (like an unavailable shop) are reported as a single string.
                // Others need to be unpacked from an array:
                if (is_array($message)) {
                    $message = $message[0]['message'];
                    // (Shopify also suggests that 400 errors may have a key like `query`, but we haven’t observed this!)
                }

                throw new ShopifyException($message);
            }

            // GraphQL responses are always nested inside a `data` key:
            if (!isset($body['data'])) {
                throw new ShopifyException('No data was returned from the GraphQL query.');
            }

            $data = $body['data'];

            // Queries and mutations have implicit “names” based on the procedure, which is where our data will be in the response.
            // The name itself doesn’t matter (we are only sending one query or mutation at a time), so we can just unwrap the “first” item:
            $data = ArrayHelper::firstValue($data);

            // The query may have selected `userErrors`, so we should check and throw:
            if (!empty($data['userErrors'])) {
                Craft::error('A GraphQL response included `userErrors`: ' . join(', ', array_column($data['userErrors'], 'message')), __METHOD__);
                throw new ShopifyException($data['userErrors'][0]['message']);
            }

            return $data;
        } catch (ClientExceptionInterface $e) {
            // We only intercept communication-related exceptions, here.
            // Everything else (like a query or mutation issue) is allowed to bubble out so it can be reported to the user.
            Craft::error('Could not run GraphQL query: ' . $e->getMessage(), __METHOD__);

            // Re-throw as an API error:
            throw new ShopifyException('An issue occurred while communicating with the Shopify API. Check the logs for more information.');
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
     * Returns or sets up a GraphQL API client.
     *
     * @see query()
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
     * @throws MissingArgumentException
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
            // Shopify wants a name for the host/environment that is *initiating* the API connection.
            // Internally, they appear to use this for starting OAuth flows and creating webhooks (but we handle the latter, manually).
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
    public function getAccessToken(?string $code = null, ?string $shop = null, bool $forceRefresh = false): ?string
    {
        // Try and retrieve the access token from the cache
        if (!$forceRefresh && $accessToken = Plugin::getInstance()->getSettings()->getAccessToken()) {
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
            /** @var AccessToken $record */
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
                // Get the first error message from the record, if available:
                $errors = $record->getErrors();
                $firstError = array_shift($errors)[0] ?? ['Unknown error'];

                Craft::error('Couldn\'t save the Shopify Access Token in the database. ' . implode(', ', $firstError), __METHOD__);
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
                'uri',
            ],
        ], function(QueryBuilder $builder) {
            $builder->setArgument('first', 100);
            $builder->setArgument('uri', Plugin::getInstance()->getSettings()->getWebhookUrl());
        });

        $response = $this->query($query);

        if (empty($response) || !isset($response['nodes'])) {
            return collect();
        }

        return collect($response['nodes']);
    }

    /**
     * @param string $id Shopify webhook subscription GID
     * @return bool
     * @throws MissingArgumentException
     * @throws ShopifyException
     * @since 6.0.0
     */
    public function deleteWebhookById(string $id): bool
    {
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

        $variables = [
            'id' => $id,
        ];

        if (!$this->query($mutation, $variables)) {
            Craft::error(sprintf('No data was returned while deleting webhook %s', $id), __METHOD__);
            throw new ShopifyException('The webhook may not have been deleted.');
        }

        return true;
    }
}
