<?php

declare(strict_types=1);

namespace CraftCms\Shopify\Product\Events;

use craft\shopify\elements\Product;
use CraftCms\Cms\Shared\Concerns\ValidatableEvent;

/**
 * Dispatched before a product element is saved with data synchronized from Shopify. Set `$isValid` to `false` to
 * skip saving it.
 *
 * @since 9.0.0
 */
class ProductSyncing
{
    use ValidatableEvent;

    public function __construct(
        /** The product element being synchronized. Changes made to it are saved. */
        public Product $element,
        /** @var array<string, mixed> The product data from the Shopify API. */
        public array $source,
    ) {
    }
}
