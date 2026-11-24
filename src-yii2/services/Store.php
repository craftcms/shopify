<?php

namespace craft\shopify\services;

/** @deprecated in 9.0.0. Use {@see \CraftCms\Shopify\Store\Store} instead. */
class_alias(\CraftCms\Shopify\Store\Store::class, 'craft\shopify\services\Store');

/** @phpstan-ignore-next-line Gives IDEs and static analysis a real symbol. */
if (false) {
    class Store extends \CraftCms\Shopify\Store\Store
    {
    }
}
