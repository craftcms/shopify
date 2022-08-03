<?php

namespace shopify\variables;


class ShopifyVariables
{
	public function getProducts($options = array())
	{
		return \shopify\Shopify::getInstance()->service->getProducts($options);
	}

	public function getProductById($options = array())
	{
		return \shopify\Shopify::getInstance()->service->getProductById($options);
    }
    
    public function getProductsFromCollectionById($options = array())
	{
		return \shopify\Shopify::getInstance()->service->getProductsFromCollectionById($options);
	}

	public function getSettings() {
        return \shopify\Shopify::getInstance()->getSettings();
    }
}