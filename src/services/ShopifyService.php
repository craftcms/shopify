<?php
/**
 * Created by PhpStorm.
 * User: nmaier
 * Date: 22.07.18
 * Time: 15:08
 */

namespace shopify\services;

use Exception;
use GuzzleHttp\Client;
use shopify\models\Settings;
use yii\base\Component;

class ShopifyService extends Component
{
    /** @var Settings */
    protected $_settings;

    /**
     * Get all products count from shopify account.
     *
     * @param array $options
     * @return bool
     */
    public function getProductsCount($options = [])
    {
        return $this->_getCount($this->_getSettings()->allProductsCountEndpoint, $options);
    }

    /**
     * Get all products from shopify account.
     *
     * @param array $options
     * @param string $link
     * @return bool
     */
    public function getProducts($options = [], $link = null)
    {
        // As long as the user doesn't pass options,
        // only get the fields we need, this almost
        // cuts the payload in half.
        if (count($options) === 0) {
            $options['fields'] = 'id,title,variants';
        }
        if (!$link) {
            if ($this->_getSettings()->limit) {
                $options['limit'] = $this->_getSettings()->getLimit();
            }
            if ($this->_getSettings()->published_status) {
                $options['published_status'] = $this->_getSettings()->getPublished_status();
            }
        }

        $query = http_build_query($options);

        if ($link) {
            $endpoint = $link . ($query ? '&' . $query : '');
        } else {
            $endpoint = $this->_getSettings()->allProductsEndpoint . ($query ? '?' . $query : '');
        }

        return $this->_getItems($endpoint);
    }

    /**
     * Get specific product from Shopify
     *
     * @param array $options
     * @return bool
     */
    public function getProductById($options = [])
    {
        $id = $options['id'];
        $fields = isset($options['fields']) ? '?fields=' . $options['fields'] : '';

        $url = $this->getShopifyUrl($this->_getSettings()->singleProductEndpoint . $id . '.json' . $fields);

        try {
            $client = new Client();
            $response = $client->request('GET', $url);

            if ($response->getStatusCode() !== 200) {
                return false;
            }

            $items = json_decode($response->getBody()->getContents(), true);

            return $items['product'];
        } catch (Exception $e) {
            \Craft::error($e->getMessage());
            return false;
        }
    }

    /**
     * Get all smart collections count from shopify account.
     *
     * @param array $options
     * @return bool
     */
    public function getSmartCollectionsCount($options = [])
    {
        return $this->_getCount($this->_getSettings()->allSmartCollectionsCountEndpoint, $options);
    }

    /**
     * Get all custom collections count from shopify account.
     *
     * @param array $options
     * @return bool
     */
    public function getCustomCollectionsCount($options = [])
    {
        return $this->_getCount($this->_getSettings()->allCustomCollectionsCountEndpoint, $options);
    }

    /**
     * Get all products from shopify account.
     *
     * @param string $endpoint
     * @param string $link
     * @param array $options
     * @return array|bool
     */
    public function getCollections($endpoint, $link, $options = [])
    {
        if (!$link && $this->_getSettings()->limit) {
            $options['limit'] = $this->_getSettings()->getLimit();
        }
        if (!$link && $this->_getSettings()->published_status) {
            $options['published_status'] = $this->_getSettings()->getPublished_status();
        }

        $query = http_build_query($options);
        if ($link) {
            $endpoint = $link . ($query ? '&' . $query : '');
        } else {
            $endpoint = $endpoint . ($query ? '?' . $query : '');
        }

        return $this->_getItems($endpoint);
    }

    /**
     * Get specific collection from Shopify
     *
     * @param array $options
     * @return array|bool
     */
    public function getCollectionById($options = [])
    {
        $id = $options['id'];
        $fields = isset($options['fields']) ? '?fields=' . $options['fields'] : '';

        $url = $this->getShopifyUrl($this->_getSettings()->singleCollectionEndpoint . $id . '.json' . $fields);

        try {
            $client = new Client();
            $response = $client->request('GET', $url);

            if ($response->getStatusCode() !== 200) {
                return false;
            }

            $items = json_decode($response->getBody()->getContents(), true);

            return $items['collection'];
        } catch (Exception $e) {
            \Craft::error($e->getMessage());
            return false;
        }
    }

    /**
     * @param $endpoint
     * @param $options
     * @return array|bool
     */
    private function _getCount($endpoint, $options)
    {
        if ($this->_getSettings()->published_status) {
            $options['published_status'] = $this->_getSettings()->getPublished_status();
        }

        $query = http_build_query($options);
        $url = $this->getShopifyUrl($endpoint . '?' . $query);

        try {
            $client = new Client();
            $response = $client->request('GET', $url);

            if ($response->getStatusCode() !== 200) {
                return false;
            }

            $items = json_decode($response->getBody()->getContents(), true);

            return $items['count'];
        } catch (Exception $e) {
            \Craft::error($e->getMessage());
            return false;
        }
    }

    /**
     * @param $endpoint
     * @return bool|array
     */
    private function _getItems($endpoint)
    {
        $url = $this->getShopifyUrl($endpoint);

        try {
            $client = new Client();

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
                'rel' => $linkRel ?? null,
            ];

            return $items;
        } catch (Exception $e) {
            \Craft::error($e->getMessage());
            return false;
        }
    }

    /**
     * @param $endpoint
     * @return string
     */
    private function getShopifyUrl($endpoint)
    {
        if (substr($endpoint, 0, 4) === 'http') {
            $endpoint = preg_split('/.com\//', $endpoint)[1];
        }

        return 'https://' .
            $this->_getSettings()->getApiKey() .
            ':' .
            $this->_getSettings()->getPassword() .
            '@' .
            $this->_getSettings()->getHostname() .
            '/' .
            $endpoint;
    }

    /**
     * @return Settings
     */
    private function _getSettings()
    {
        if ($this->_settings === null) {
            $this->_settings = \shopify\Shopify::getInstance()->getSettings();
        }

        return $this->_settings;
    }
}
