<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\shopify\api;

use MaxGraphQL\Types\Mutation;

/**
 * Shopify GraphQl API Mutation.
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 6.0.0
 */
class ShopifyMutation extends Mutation
{
    public array $typeArguments = [];

    /**
     * @param array $typeArguments
     * @return self
     */
    public function addTypeArguments(array $typeArguments): self
    {
        $this->typeArguments = $typeArguments;
        return $this;
    }

    /**
     * @return array
     */
    public function getTypeArguments(): array
    {
        return $this->typeArguments;
    }

    public function getType()
    {
        $type = parent::getType();

    }
}