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
            case Topics::PRODUCTS_UPDATE:
            case Topics::PRODUCTS_CREATE:
                Plugin::getInstance()->getProducts()->createOrUpdateProduct($body);
                break;
            case Topics::PRODUCTS_DELETE:
                Plugin::getInstance()->getProducts()->deleteProductByShopifyId($body['id']);
                break;
        }
    }
}
