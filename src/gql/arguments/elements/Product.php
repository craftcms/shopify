<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\shopify\gql\arguments\elements;

use Craft;
use craft\shopify\elements\Product as ProductElement;
use craft\shopify\Plugin;
use craft\gql\base\ElementArguments;
use craft\gql\types\QueryArgument;
use GraphQL\Type\Definition\Type;

/**
 * Class Product
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 6.1.0
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
