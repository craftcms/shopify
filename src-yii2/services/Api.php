<?php

namespace craft\shopify\services;

/** @deprecated in 9.0.0. Use {@see \CraftCms\Shopify\Api\Api} instead. */
class_alias(\CraftCms\Shopify\Api\Api::class, 'craft\shopify\services\Api');

/** @phpstan-ignore-next-line Gives IDEs and static analysis a real symbol. */
if (false) {
    class Api extends \CraftCms\Shopify\Api\Api
    {
    }
}
