<?php
/**
 * Created by PhpStorm.
 * User: nmaier
 * Date: 22.07.18
 * Time: 14:20
 */

namespace shopify\models;

use Craft;
use craft\base\Model;

class Settings extends Model
{
    public $apiKey = '';
    public $password = '';
    public $secret = '';
    public $hostname = '';
    public $limit = '';
    public $published_status = '';

    public function getApiKey(): string
    {
        return Craft::parseEnv($this->apiKey);
    }
    public function getPassword(): string
    {
        return Craft::parseEnv($this->password);
    }
    public function getSecret(): string
    {
        return Craft::parseEnv($this->secret);
    }
    public function getHostname(): string
    {
        return Craft::parseEnv($this->hostname);
    }
    public function getLimit(): string
    {
        return Craft::parseEnv($this->limit);
    }
    public function getPublished_status(): string
    {
        return Craft::parseEnv($this->published_status);
    }

    public $apiPrefix = 'admin/api';
    public $apiVersion = '2020-01';
    public $allProductsEndpoint;
    public $allProductsCountEndpoint;
    public $singleProductEndpoint;

    public function __construct()
    {
        $apiStart = $this->apiPrefix . '/' . $this->apiVersion;
        $this->allProductsEndpoint = $apiStart . '/products.json';
        $this->allProductsCountEndpoint = $apiStart . '/products/count.json';
        $this->singleProductEndpoint = $apiStart . '/products/';
    }

    public function rules()
    {
        return [[['apiKey', 'password', 'secret', 'hostname'], 'required'], ['hostname', 'validateHostname']];
    }

    /**
     * @param $attribute
     * @param $params
     */
    public function validateHostname($attributeName)
    {
        if (
            strpos($this->getHostname(), '//') !== false ||
            strpos($this->getHostname(), 'http://') !== false ||
            strpos($this->getHostname(), 'https://') !== false
        ) {
            $this->addError(
                $attributeName,
                'Please do not use http://, https:// or // at the beginning of the hostname.'
            );
        }
    }
}
