<?php

namespace craft\shopify\models;

/** @deprecated in 9.0.0. Use {@see \CraftCms\Shopify\Settings} instead. */
class_alias(\CraftCms\Shopify\Settings::class, 'craft\shopify\models\Settings');

/** @phpstan-ignore-next-line Gives IDEs and static analysis a real symbol. */
if (false) {
    class Settings extends \CraftCms\Shopify\Settings
    {
    }
}
