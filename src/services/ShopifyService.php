<?php
/**
 * Created by PhpStorm.
 * User: nmaier
 * Date: 22.07.18
 * Time: 15:08
 */

namespace shopify\services;

use Craft;
use yii\base\Component;

class ShopifyService extends Component
{

    /**
     * get all products from shopify account
     *
     * @param array $options
     * @return bool
     */
    public function getProducts($options = array())
    {
        $settings = \shopify\Shopify::getInstance()->getSettings();

        $query = http_build_query($options);
        $url = $this->getShopifyUrl($settings->allProductsEndpoint . '?' . $query, $settings);

        try {
            $client = new \GuzzleHttp\Client();
            $response = $client->request('GET', $url);

            if ($response->getStatusCode() !== 200) {
                return false;
            }


            $items = json_decode($response->getBody()->getContents(), true);

            return $items['products'];
        } catch(\Exception $e) {
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
        } catch(\Exception $e) {
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
        return 'https://' . $settings->apiKey . ':' . $settings->password . '@' . $settings->hostname . '/' . $endpoint;
    }
}