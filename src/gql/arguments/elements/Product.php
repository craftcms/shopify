<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\shopify\gql\arguments\elements;

use Craft;
use craft\gql\base\ElementArguments;
use craft\gql\types\QueryArgument;
use craft\shopify\elements\Product as ProductElement;
use craft\shopify\Plugin;
use GraphQL\Type\Definition\Type;

/**
 * Class Product
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 7.1.0
 */
class Product extends ElementArguments
{
    /**
     * @inheritdoc
     */
    public static function getArguments(): array
    {
        return array_merge(parent::getArguments(), self::getContentArguments(), [
            'shopifyId' => [
                'name' => 'shopifyId',
                'type' => Type::listOf(QueryArgument::getType()),
                'description' => 'Narrows the query results based on the Shopify ID on the product.',
            ],
            'shopifyGid' => [
                'name' => 'shopifyGid',
                'type' => Type::listOf(QueryArgument::getType()),
                'description' => 'Narrows the query results based on the Shopify GID on the product.',
            ],
            'shopifyStatus' => [
                'name' => 'shopifyStatus',
                'type' => Type::listOf(QueryArgument::getType()),
                'description' => 'Narrows the query results based on the Shopify status of the product.',
            ],
            'handle' => [
                'name' => 'handle',
                'type' => Type::listOf(QueryArgument::getType()),
                'description' => 'Narrows the query results based on the handle on the product.',
            ],
            'productType' => [
                'name' => 'productType',
                'type' => Type::listOf(QueryArgument::getType()),
                'description' => 'Narrows the query results based on the product type on the product.',
            ],
            'tags' => [
                'name' => 'tags',
                'type' => Type::listOf(QueryArgument::getType()),
                'description' => 'Narrows the query results based on the tags on the product.',
            ],
            'vendor' => [
                'name' => 'vendor',
                'type' => Type::listOf(QueryArgument::getType()),
                'description' => 'Narrows the query results based on the vendor on the product.',
            ],
            'publishedOnCurrentPublication' => [
                'name' => 'publishedOnCurrentPublication',
                'type' => Type::boolean(),
                'description' => 'Narrows the query results based on the published on current publication on the product.',
            ],
        ]);
    }

    /**
     * @inheritdoc
     */
    public static function getContentArguments(): array
    {
        $productFieldsArguments = Craft::$app->getGql()->getContentArguments([
            Plugin::getInstance()->getSettings()->getProductFieldLayout(),
        ], ProductElement::class);

        return array_merge(parent::getContentArguments(), $productFieldsArguments);
    }
}
