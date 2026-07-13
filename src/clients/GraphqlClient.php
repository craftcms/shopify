<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\shopify\clients;

use craft\shopify\exceptions\ShopifyApiException;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;

/**
 * Shopify Admin GraphQL API client.
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 8.0.0
 */
class GraphqlClient
{
    private Client $_client;

    public function __construct(
        string $shop,
        string $accessToken,
        private string $apiVersion,
    ) {
        $this->_client = new Client([
            'base_uri' => "https://{$shop}",
            'headers' => [
                'X-Shopify-Access-Token' => $accessToken,
                'Content-Type' => 'application/json',
                'X-Shopify-Api-Features' => 'include-presentment-prices',
            ],
        ]);
    }

    /**
     * Executes a GraphQL query against the Shopify Admin API.
     *
     * @param array $data An array with at minimum a `query` key, optionally `variables`.
     * @return array The decoded response body.
     * @throws ShopifyApiException on HTTP or communication failure.
     */
    public function query(array $data, array $extraHeaders = []): array
    {
        try {
            $options = ['json' => $data];
            if ($extraHeaders) {
                $options['headers'] = $extraHeaders;
            }

            $response = $this->_client->post(
                "admin/api/{$this->apiVersion}/graphql.json",
                $options,
            );

            return json_decode((string)$response->getBody(), true) ?? [];
        } catch (GuzzleException $e) {
            throw new ShopifyApiException($e->getMessage(), $e->getCode(), $e);
        }
    }
}
