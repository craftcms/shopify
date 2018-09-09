<?php
/**
 * Created by PhpStorm.
 * User: nmaier
 * Date: 22.07.18
 * Time: 14:20
 */

namespace shopify\models;

use craft\base\Model;

class Settings extends Model
{
    public $apiKey;

    public $password;

    public $secret;

    public $hostname; // valid-sample: craftintegration.myshopify.com //TODO validate user input to match format from sample

    public $allProductsEndpoint = 'admin/products.json';

    public $singleProductEndpoint = 'admin/products/';


    public function rules()
    {
        return [
            [['apiKey', 'password', 'secret', 'hostname'], 'required'],
            ['hostname', 'validateHostname']
        ];
    }


    /**
     * @param $attribute
     * @param $params
     */
    public function validateHostname($attributeName)
    {
        if(strpos($this->hostname, '//') !== false ||
            strpos($this->hostname, 'http://') !== false ||
            strpos($this->hostname, 'https://') !== false) {
            $this->addError($attributeName, 'Please do not use http://, https:// or // at the beginning of the hostname.');
        }
    }

}