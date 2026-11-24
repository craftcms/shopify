<?php

namespace craft\shopify\services;

/** @deprecated in 9.0.0. Use {@see \CraftCms\Shopify\BulkOperation\BulkOperations} instead. */
class_alias(\CraftCms\Shopify\BulkOperation\BulkOperations::class, 'craft\shopify\services\BulkOperations');

/** @phpstan-ignore-next-line Gives IDEs and static analysis a real symbol. */
if (false) {
    class BulkOperations extends \CraftCms\Shopify\BulkOperation\BulkOperations
    {
    }
}
