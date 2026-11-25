<?php

declare(strict_types=1);

namespace CraftCms\Shopify\Api\Events;

/**
 * Dispatched while building the GraphQL query that fetches products from Shopify, so the selected fields can be
 * changed.
 *
 * @since 9.0.0
 */
class ProductGqlFieldsResolving
{
    public function __construct(
        /** @var array<array-key, mixed> The fields to select, nested by connection. */
        public array $fields,
    ) {
    }
}
