<?php

namespace craft\shopify\helpers;

/** @deprecated in 9.0.0. Use {@see \CraftCms\Shopify\Helpers\Metafield} instead. */
class_alias(\CraftCms\Shopify\Helpers\Metafield::class, 'craft\shopify\helpers\Metafield');

/** @phpstan-ignore-next-line Gives IDEs and static analysis a real symbol. */
if (false) {
    class Metafield extends \CraftCms\Shopify\Helpers\Metafield
    {
    }
}
