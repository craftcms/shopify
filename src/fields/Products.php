<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\shopify\fields;

use Craft;
use craft\fields\BaseRelationField;
use craft\helpers\Gql;
use craft\shopify\elements\Product;
use craft\shopify\gql\arguments\elements\Product as ProductArguments;
use craft\shopify\gql\interfaces\elements\Product as ProductInterface;
use craft\shopify\gql\resolvers\elements\Product as ProductResolver;
use GraphQL\Type\Definition\Type;

/**
 * Class Shopify Product Field
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 3.0
 *
 * @property-read array $contentGqlType
 */
class Products extends BaseRelationField
{
    /**
     * @inheritdoc
     */
    public static function displayName(): string
    {
        return Craft::t('shopify', 'Shopify Products');
    }

    /**
     * @inheritdoc
     */
    public static function defaultSelectionLabel(): string
    {
        return Craft::t('shopify', 'Add a product');
    }

    /**
     * @inheritdoc
     */
    public static function elementType(): string
    {
        return Product::class;
    }

    /**
     * @inheritdoc
     * @since 7.1.0
     */
    public function getContentGqlType(): Type|array
    {
        return [
            'name' => $this->handle,
            'type' => Type::listOf(ProductInterface::getType()),
            'args' => ProductArguments::getArguments(),
            'resolve' => ProductResolver::class . '::resolve',
            'complexity' => Gql::eagerLoadComplexity(),
        ];
    }
}
