<?php

declare(strict_types=1);

namespace CraftCms\Shopify\Api;

use craft\shopify\auth\OAuthFlow;
use craft\shopify\clients\GraphqlClient;
use craft\shopify\enums\ApiVersion;
use craft\shopify\enums\WebhookTopics;
use craft\shopify\exceptions\ShopifyApiCommunicationException;
use craft\shopify\exceptions\ShopifyApiException;
use CraftCms\Cms\Support\Env;
use CraftCms\Cms\Support\Str;
use CraftCms\Shopify\Api\Events\GqlQueryArgumentsResolving;
use CraftCms\Shopify\Api\Events\ProductGqlFieldsResolving;
use CraftCms\Shopify\Api\Models\AccessToken;
use CraftCms\Shopify\Api\Models\ShopifyData;
use CraftCms\Shopify\Settings;
use GraphQL\Mutation;
use GraphQL\Query;
use GraphQL\QueryBuilder\QueryBuilder;
use GraphQL\Variable;
use Illuminate\Container\Attributes\Singleton;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

use function CraftCms\Cms\t;

/**
 * Shopify API service.
 */
#[Singleton]
class Api
{
    public const string API_ACCESS_TOKEN_ENV_VAR = 'SHOPIFY_API_ACCESS_TOKEN';

    private ?string $accessToken = null;

    private ?GraphqlClient $gqlClient = null;

    public function __construct(
        private readonly Settings $settings,
    ) {
    }

    /**
     * @return string[]
     * @since 5.3.0
     */
    public function getSupportedApiVersions(): array
    {
        return array_column(ApiVersion::cases(), 'value');
    }

    /**
     * @return WebhookTopics[]
     * @since 8.0.0
     */
    public function getWebhookTopics(): array
    {
        return WebhookTopics::cases();
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
     * @return Query
     * @since 7.2.0
     */
    public function getShopLocalesGql(): Query
    {
        return $this->createQuery('shopLocales', [
            'locale',
            'primary',
        ], function(QueryBuilder $builder) {
            $builder->setArgument('published', true);
        });
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
        $shopRecord = ShopifyData::query()->where('type', 'Shop')->first();
        if ($shopRecord && !$update) {
            return $shopRecord->data;
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

            $shopRecord->shopifyGid = $response['id'];
            $shopRecord->type = 'Shop';
            $shopRecord->data = $response;

            $shopRecord->save();

            $shop = $shopRecord->data;
        } catch (\Exception $e) {
            Log::error('Failed to sync Shopify shop data: ' . $e->getMessage());
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
        // Create contextual pricing fields (if required)
        $contextualPricingCountries = $this->settings->getContextualPricingCountries();
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

        // Create translations fields (if required)
        $translations = [];
        if (in_array('productTranslations', $this->settings->getAdditionalFeatures())) {
            try {
                $cacheKey = 'shopify:shopLocales:' . $this->settings->getHostName();
                $locales = Cache::remember($cacheKey, 86400, fn() => $this->query($this->getShopLocalesGql()));

                if (empty($locales)) {
                    throw new \Exception('Shop locales data not found in the response.');
                }

                foreach ($locales as $locale) {
                    if ($locale['primary']) {
                        continue;
                    }

                    $localeKey = sprintf('translations_%1$s: translations(locale:"%1$s")', $locale['locale']);
                    $translations[$localeKey] = ['key', 'value'];
                }
            } catch (\Exception $e) {
                Log::error($e->getMessage());
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
                    // Add translations to the products query
                    ...$translations,
                ],
            ],
        ];

        event($event = new ProductGqlFieldsResolving($fields));
        $fields = $event->fields;

        return $this->createQuery('products', $fields, function(QueryBuilder $builder) use ($id) {
            if ($id) {
                // Extract the numeric ID from a full GID or pass through a bare numeric ID
                $id = Str::afterLast($id, '/');

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
    private function prepQueryBuilder($key, $value, QueryBuilder $builderQuery): void
    {
        if (is_array($value)) {
            $subQueryBuilder = (new QueryBuilder($key));
            foreach ($value as $k => $v) {
                $this->prepQueryBuilder($k, $v, $subQueryBuilder);
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
    public function createQuery(string $name, array $fields, ?callable $beforeFields = null): Query
    {
        $builder = new QueryBuilder($name);

        if ($beforeFields !== null) {
            $beforeFields($builder);
        }

        foreach ($fields as $key => $value) {
            $this->prepQueryBuilder($key, $value, $builder);
        }

        $query = $builder->getQuery();

        event($event = new GqlQueryArgumentsResolving($query->getFieldName(), $query->getArguments()));
        $query->setArguments($event->arguments);

        return $query;
    }

    /**
     * Run a GraphQL query against the Shopify API.
     *
     * If you need to control how a response is unpacked, use {@see getGqlClient()} directly.
     *
     * Under normal circumstances, the selected fields (including `userErrors`, when requested) are returned as an array.
     *
     * @param Query|string $query
     * @param array|null $variables
     * @return mixed Typically an array with the same structure as the selection, or `null` for nonexistent nodes.
     * @throws ShopifyApiException when the response looks unusual (i.e. an `errors` key is present, a `data` key was not returned, or `userErrors` was populated), or on a low-level communication failure
     * @throws \RuntimeException if a session can't be established
     * @since 6.0.0
     */
    public function query(Query|string $query, ?array $variables = null): mixed
    {
        // An invalid session will cause everything to fail:
        if (!$this->connect()) {
            throw new RuntimeException(t('No Shopify session available. Please check your credentials and re-authorize the application, if necessary.', category: 'shopify'));
        }

        $payload = ['query' => (string)$query];

        if ($variables) {
            $payload['variables'] = $variables;
        }

        try {
            $body = $this->getGqlClient()->query($payload);

            if (array_key_exists('errors', $body)) {
                $message = $body['errors'];

                // https://shopify.dev/docs/api/admin-graphql/2025-10#status-and-error-codes
                // Some low-level errors (like an unavailable shop) are reported as a single string.
                // Others need to be unpacked from an array:
                if (is_array($message)) {
                    $message = $message[0]['message'];
                    // (Shopify also suggests that 400 errors may have a key like `query`, but we haven't observed this!)
                }

                throw new ShopifyApiException($message);
            }

            // GraphQL responses are always nested inside a `data` key:
            if (!isset($body['data'])) {
                throw new ShopifyApiException('No data was returned from the GraphQL query.');
            }

            $data = $body['data'];

            // Queries and mutations have implicit "names" based on the procedure, which is where our data will be in the response.
            // The name itself doesn't matter (we are only sending one query or mutation at a time), so we can just unwrap the "first" item:
            $data = Arr::first($data);

            // The query may have selected `userErrors`, so we should check and throw:
            if (!empty($data['userErrors'])) {
                Log::error('A GraphQL response included `userErrors`: ' . join(', ', array_column($data['userErrors'], 'message')));
                throw new ShopifyApiException($data['userErrors'][0]['message']);
            }

            return $data;
        } catch (ShopifyApiCommunicationException $e) {
            // We only intercept communication-related exceptions, here.
            // Everything else (like a query or mutation issue) is allowed to bubble out so it can be reported to the user.
            Log::error('Could not run GraphQL query: ' . $e->getMessage());

            // Re-throw as a generic API error:
            throw new ShopifyApiException('An issue occurred while communicating with the Shopify API. Check the logs for more information.', 0, $e);
        }
    }

    /**
     * Queries the data table for records of the specified type, optionally owned by one or more "parent" objects.
     *
     * @param string $type
     * @param string|false|null $parentId
     * @param bool $returnRecords
     * @return Collection
     * @since 6.0.0
     */
    public function getShopifyDataByType(string $type, array|string|null|false $parentId = null, bool $returnRecords = false): Collection
    {
        $data = ShopifyData::query()
            ->where('type', $type)
            ->when($parentId !== null, function($query) use ($parentId) {
                if (!is_array($parentId) || $parentId === []) {
                    $query->where('parentId', $parentId ?: null);
                    return;
                }

                $query->where(fn($query) => $query
                    ->whereIn('parentId', array_filter($parentId, fn($id) => $id !== null))
                    ->when(in_array(null, $parentId, true), fn($query) => $query->orWhereNull('parentId')));
            })
            ->orderBy('position')
            ->orderBy('id')
            ->get();

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
     * @return GraphqlClient
     * @since 6.0.0
     */
    public function getGqlClient(): GraphqlClient
    {
        if ($this->gqlClient === null) {
            if (!$this->accessToken) {
                throw new RuntimeException('Unable to initialize API session. Check that your API credentials are correct and that you have authorized the app.');
            }

            $this->gqlClient = new GraphqlClient(
                $this->settings->getHostName(true),
                $this->accessToken,
                $this->settings->getApiVersion(),
            );
        }

        return $this->gqlClient;
    }

    /**
     * Ensures the service is initialized with a shop hostname and access token.
     *
     * Returns true if the plugin is authorized and ready to make API calls, false otherwise.
     *
     * @return bool
     * @since 8.0
     */
    public function connect(): bool
    {
        if ($this->accessToken !== null) {
            return true;
        }

        if (!$this->settings->getClientId(true) || !$this->settings->getClientSecret(true)) {
            return false;
        }

        $accessToken = $this->getAccessToken(shop: $this->settings->getHostName(true));

        if ($accessToken) {
            $this->accessToken = $accessToken;
        }

        return $this->accessToken !== null;
    }

    /**
     * @param string|null $code
     * @param string|null $shop
     * @return string|null
     * @since 7.0.0
     */
    public function getAccessToken(?string $code = null, ?string $shop = null, bool $forceRefresh = false): ?string
    {
        // Try and retrieve the access token from the cache
        if (!$forceRefresh && $accessToken = $this->settings->getAccessToken()) {
            return $accessToken;
        }

        if (!$code || !$shop) {
            return null;
        }

        try {
            /** @phpstan-ignore staticMethod.notFound (Craft registers `create()` as a macro.) */
            $body = Http::create()->post('https://' . $shop . OAuthFlow::ACCESS_TOKEN_POST_PATH, [
                'client_id' => $this->settings->getClientId(true),
                'client_secret' => $this->settings->getClientSecret(true),
                'code' => $code,
                'expiring' => 0,
            ])->json();

            if (!isset($body['access_token'])) {
                throw new \Exception('No access token returned from Shopify.');
            }

            $record = AccessToken::query()->first() ?? new AccessToken();

            // If there isn't a `.env` file, let's not try and save it there in case that is by design
            $dotEnvPath = app()->environmentFilePath();
            $hasDotEnv = file_exists($dotEnvPath);

            $isSavedToFile = true;
            if (!$hasDotEnv) {
                $isSavedToFile = false;
            } else {
                try {
                    Env::writeVariable(self::API_ACCESS_TOKEN_ENV_VAR, $body['access_token'], $dotEnvPath, overwrite: true);
                    $_SERVER[self::API_ACCESS_TOKEN_ENV_VAR] = $body['access_token'];
                } catch (\Throwable $e) {
                    $isSavedToFile = false;
                    Log::error('Couldn\'t save the Shopify Access Token in the .env file. ' . $e->getMessage());
                }
            }

            $record->accessToken = $isSavedToFile ? '$' . self::API_ACCESS_TOKEN_ENV_VAR : Str::encenc($body['access_token']);

            $record->save();

            return $body['access_token'];
        } catch (\Exception $e) {
            Log::error('Could not get access token from Shopify: ' . $e->getMessage());
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
            $builder->setArgument('uri', $this->settings->getWebhookUrl());
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
     * @throws ShopifyApiException
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
            Log::error(sprintf('No data was returned while deleting webhook %s', $id));
            throw new ShopifyApiException('The webhook may not have been deleted.');
        }

        return true;
    }
}
