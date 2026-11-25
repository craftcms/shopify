<?php

namespace craft\shopify\records;

/** @deprecated in 9.0.0. Use {@see \CraftCms\Shopify\Api\Models\AccessToken} instead. */
class_alias(\CraftCms\Shopify\Api\Models\AccessToken::class, 'craft\shopify\records\AccessToken');

/** @phpstan-ignore-next-line Gives IDEs and static analysis a real symbol. */
if (false) {
    class AccessToken extends \CraftCms\Shopify\Api\Models\AccessToken
    {
    }
}
