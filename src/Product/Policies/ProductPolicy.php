<?php

declare(strict_types=1);

namespace CraftCms\Shopify\Product\Policies;

use CraftCms\Cms\Element\Policies\ElementPolicy;
use CraftCms\Cms\User\Contracts\CraftUser;
use CraftCms\Shopify\Product\Elements\Product;

/**
 * Products are created and deleted in Shopify, so users with access to Shopify can only edit them, and delete drafts.
 */
class ProductPolicy extends ElementPolicy
{
    public function view(CraftUser $user, Product $product): bool
    {
        return $user->can('accessPlugin-shopify');
    }

    public function save(CraftUser $user, Product $product): bool
    {
        return $user->can('accessPlugin-shopify');
    }

    public function createDrafts(CraftUser $user, Product $product): bool
    {
        return $user->can('accessPlugin-shopify');
    }

    public function delete(CraftUser $user, Product $product): bool
    {
        return $product->getIsDraft() && $user->can('accessPlugin-shopify');
    }
}
