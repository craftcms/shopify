<?php

namespace craft\shopify\helpers;

/** @deprecated in 9.0.0. Use {@see \CraftCms\Shopify\Helpers\ShopifyHelper} instead. */
class_alias(\CraftCms\Shopify\Helpers\ShopifyHelper::class, 'craft\shopify\helpers\ShopifyHelper');

/** @phpstan-ignore-next-line Gives IDEs and static analysis a real symbol. */
if (false) {
    class ShopifyHelper extends \CraftCms\Shopify\Helpers\ShopifyHelper
    {
    }
}
