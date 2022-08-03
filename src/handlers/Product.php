<?php

namespace craft\shopify\handlers;

use craft\shopify\Plugin;
use Shopify\Webhooks\Handler;
use Shopify\Webhooks\Topics;

class Product implements Handler
{
    public function handle(string $topic, string $shop, array $body): void
    {
        switch ($topic) {
            case Topics::PRODUCTS_CREATE:
                $this->_handleProductCreate($topic, $shop, $body);
                break;
            case Topics::PRODUCTS_UPDATE:
                $this->_handleProductUpdate($topic, $shop, $body);
                break;
            case Topics::PRODUCTS_DELETE:
                $this->_handleProductDelete($topic, $shop, $body);
                break;
        }
    }

    private function _handleProductCreate(string $topic, string $shop, array $body)
    {
        Plugin::getInstance()->getProducts()->createOrUpdateProduct($body);
    }

    private function _handleProductUpdate(string $topic, string $shop, array $body)
    {
        Plugin::getInstance()->getProducts()->createOrUpdateProduct($body);
    }

    private function _handleProductDelete(string $topic, string $shop, array $body)
    {
        Plugin::getInstance()->getProducts()->deleteProductByShopifyId($body['id']);
    }
}