<?php

namespace craft\shopify\api;

/** @deprecated in 9.0.0. Use {@see \CraftCms\Shopify\BulkOperation\BulkDataBatcher} instead. */
class_alias(\CraftCms\Shopify\BulkOperation\BulkDataBatcher::class, 'craft\shopify\api\BulkDataBatcher');

/** @phpstan-ignore-next-line Gives IDEs and static analysis a real symbol. */
if (false) {
    class BulkDataBatcher extends \CraftCms\Shopify\BulkOperation\BulkDataBatcher
    {
    }
}
