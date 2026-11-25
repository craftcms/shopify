<?php

namespace craft\shopify\records;

/** @deprecated in 9.0.0. Use {@see \CraftCms\Shopify\BulkOperation\Models\BulkOperation} instead. */
class_alias(\CraftCms\Shopify\BulkOperation\Models\BulkOperation::class, 'craft\shopify\records\BulkOperation');

/** @phpstan-ignore-next-line Gives IDEs and static analysis a real symbol. */
if (false) {
    class BulkOperation extends \CraftCms\Shopify\BulkOperation\Models\BulkOperation
    {
    }
}
