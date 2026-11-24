<?php

namespace craft\shopify;

/** @deprecated in 9.0.0. Use {@see \CraftCms\Shopify\Plugin} instead. */
class_alias(\CraftCms\Shopify\Plugin::class, 'craft\shopify\Plugin');

/** @phpstan-ignore-next-line Gives IDEs and static analysis a real symbol. */
if (false) {
    class Plugin extends \CraftCms\Shopify\Plugin
    {
    }
}
