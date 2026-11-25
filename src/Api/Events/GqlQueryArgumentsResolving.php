<?php

declare(strict_types=1);

namespace CraftCms\Shopify\Api\Events;

use BackedEnum;
use Stringable;

/**
 * Dispatched while building any GraphQL query sent to Shopify, so its arguments can be changed.
 *
 * @since 9.0.0
 */
class GqlQueryArgumentsResolving
{
    public function __construct(
        /** The name of the queried field, such as `products`. */
        public string $fieldName,
        /** @var array<string, null|scalar|array<?scalar>|Stringable|BackedEnum> */
        public array $arguments,
    ) {
    }
}
