<?php

namespace shopify\variables;

use Craft;

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
}