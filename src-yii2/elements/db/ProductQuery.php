<?php

namespace craft\shopify\elements\db;

/** @deprecated in 9.0.0. Use {@see \CraftCms\Shopify\Product\Queries\ProductQuery} instead. */
class_alias(\CraftCms\Shopify\Product\Queries\ProductQuery::class, 'craft\shopify\elements\db\ProductQuery');

/** @phpstan-ignore-next-line Gives IDEs and static analysis a real symbol. */
if (false) {
    class ProductQuery extends \CraftCms\Shopify\Product\Queries\ProductQuery
    {
    }
}
