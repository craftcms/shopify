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

        if ($settings->published_status) {
            $options['published_status'] = $settings->getPublished_status();
        }

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
    public function getProducts($options = [], $link = null)
    {
        $settings = \shopify\Shopify::getInstance()->getSettings();

        if (!$link && $settings->limit) {
            $options['limit'] = $settings->getLimit();
        }
        if (!$link && $settings->published_status) {
            $options['published_status'] = $settings->getPublished_status();
        }

        $query = http_build_query($options);
        if ($link) {
            $endpoint = $link . ($query ? '&' . $query : '');
        } else {
            $endpoint = $settings->allProductsEndpoint . ($query ? '?' . $query : '');
        }

        $url = $this->getShopifyUrl($endpoint, $settings);

        try {
            $client = new \GuzzleHttp\Client();

            $response = $client->request('GET', $url, [
                // 'debug' => true
            ]);

            if ($response->getStatusCode() !== 200) {
                return false;
            }
            $link = $response->getHeader('Link') ? $response->getHeader('Link') : null;

            if ($link && count(preg_grep('/"next"/', $link)) > 0) {
                $splitLink = preg_split('/; rel=/', $link[0]);
                if (count(preg_grep('/"previous"/', $splitLink)) > 0) {
                    $linkNextUrl = trim(str_replace(['"previous", '], '', $splitLink[1]), '<>');
                    $linkRel = trim(str_replace(['"'], '', $splitLink[2]));
                } else {
                    $linkNextUrl = trim($splitLink[0], '<>');
                    $linkRel = trim(str_replace(['"'], '', $splitLink[1]));
                }
            }
            $items = json_decode($response->getBody()->getContents(), true);
            $items['link'] = [
                'url' => $linkNextUrl ?? null,
                'rel' => $linkRel ?? null
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

        return 'https://' .
            $settings->getApiKey() .
            ':' .
            $settings->getPassword() .
            '@' .
            $settings->getHostname() .
            '/' .
            $endpoint;
    }
}
