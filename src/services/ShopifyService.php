<?php
/**
 * Created by PhpStorm.
 * User: nmaier
 * Date: 22.07.18
 * Time: 15:08
 */

namespace shopify\services;

use yii\base\Component;

class ShopifyService extends Component
{
    /**
     * Get all products count from shopify account.
     *
     * @param array $options
     * @return bool
     */
    public function getProductsCount($options = array())
    {
        $settings = \shopify\Shopify::getInstance()->getSettings();

        $query = http_build_query($options);
        $url = $this->getShopifyUrl($settings->allProductsCountEndpoint . '?' . $query, $settings);

        try {
            $client = new \GuzzleHttp\Client();
            $response = $client->request('GET', $url);

            if ($response->getStatusCode() !== 200) {
                return false;
            }

            $items = json_decode($response->getBody()->getContents(), true);

            return $items['count'];
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Get all products from shopify account.
     *
     * @param array $options
     * @return bool
     */
    public function getProducts($options = array(), $link = null)
    {
        $settings = \shopify\Shopify::getInstance()->getSettings();
        $query = http_build_query($options);
        $endpoint = $link ?? $settings->allProductsEndpoint;
        $url = $this->getShopifyUrl($endpoint . ($query ? '?' . $query : ''), $settings);

        try {
            $client = new \GuzzleHttp\Client();

            $response = $client->request('GET', $url, [
                // 'debug' => true
            ]);

            if ($response->getStatusCode() !== 200) {
                return false;
            }

            $link = $response->getHeader('Link') ? preg_split('/;/', $response->getHeader('Link')[0]) : null;
            $linkUrl = trim($link[0], '<>');
            $linkRel = trim(str_replace(['rel="', '"'], '', $link[1]));

            $items = json_decode($response->getBody()->getContents(), true);
            $items['link'] = [
                'url' => $linkUrl,
                'rel' => $linkRel,
            ];

            return $items;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Get specific product from Shopify
     *
     * @param array $options
     * @return bool
     */
    public function getProductById($options = array())
    {
        $settings = \shopify\Shopify::getInstance()->getSettings();

        $id = $options['id'];
        $fields = isset($options['fields']) ? '?fields=' . $options['fields'] : '';

        $url = $this->getShopifyUrl($settings->singleProductEndpoint . $id . '.json' . $fields, $settings);

        try {
            $client = new \GuzzleHttp\Client();
            $response = $client->request('GET', $url);

            if ($response->getStatusCode() !== 200) {
                return false;
            }

            $items = json_decode($response->getBody()->getContents(), true);

            return $items['product'];
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * @param $endpoint
     * @param \shopify\models\settings $settings
     * @return string
     */
    private function getShopifyUrl($endpoint, \shopify\models\settings $settings)
    {
        if (substr($endpoint, 0, 4) === 'http') {
            $endpoint = preg_split('/.com\//', $endpoint)[1];
        }
        return 'https://' . $settings->apiKey . ':' . $settings->password . '@' . $settings->hostname . '/' . $endpoint;
    }
}
