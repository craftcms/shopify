<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\shopify\gql\queries;

use craft\gql\base\Query;
use craft\helpers\Gql;
use craft\shopify\gql\arguments\elements\Product as ProductArguments;
use craft\shopify\gql\interfaces\elements\Product as ProductInterface;
use craft\shopify\gql\resolvers\elements\Product as ProductResolver;
use GraphQL\Type\Definition\Type;

/**
 * Class Product
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 7.1.0
 */
class Product extends Query
{
    /**
     * @inheritdoc
     */
    public static function getQueries(bool $checkToken = true): array
    {
        $entities = Gql::extractAllowedEntitiesFromSchema();
        $typeName = (new \craft\shopify\elements\Product())->getGqlTypeName();
        if ($checkToken && !isset($entities[$typeName])) {
            return [];
        }

        return [
            'shopifyProducts' => [
                'type' => Type::listOf(ProductInterface::getType()),
                'args' => ProductArguments::getArguments(),
                'resolve' => ProductResolver::class . '::resolve',
                'description' => 'This query is used to query for products.',
            ],
            'shopifyProductCount' => [
                'type' => Type::nonNull(Type::int()),
                'args' => ProductArguments::getArguments(),
                'resolve' => ProductResolver::class . '::resolveCount',
                'description' => 'This query is used to return the number of products.',
            ],
            'shopifyProduct' => [
                'type' => ProductInterface::getType(),
                'args' => ProductArguments::getArguments(),
                'resolve' => ProductResolver::class . '::resolveOne',
                'description' => 'This query is used to query for a product.',
            ],
        ];
    }
}
