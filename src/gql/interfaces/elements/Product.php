<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\shopify\gql\interfaces\elements;

use Craft;
use craft\gql\GqlEntityRegistry;
use craft\gql\interfaces\Element;
use craft\shopify\elements\Product as ProductElement;
use craft\shopify\gql\types\generators\ProductType;
use GraphQL\Type\Definition\InterfaceType;
use GraphQL\Type\Definition\Type;

/**
 * Class Product
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 6.1.0
 */
class Product extends Element
{
    /**
     * @inheritdoc
     */
    public static function getTypeGenerator(): string
    {
        return ProductType::class;
    }

    /**
     * @inheritdoc
     */
    public static function getType($fields = null): Type
    {
        if ($type = GqlEntityRegistry::getEntity(self::getName())) {
            return $type;
        }

        $type = GqlEntityRegistry::createEntity(self::getName(), new InterfaceType([
            'name' => static::getName(),
            'fields' => self::class . '::getFieldDefinitions',
            'description' => 'This is the interface implemented by all products.',
            'resolveType' => function(ProductElement $value) {
                return $value->getGqlTypeName();
            },
        ]));

        ProductType::generateTypes();

        return $type;
    }

    /**
     * @inheritdoc
     */
    public static function getName(): string
    {
        return 'ShopifyProductInterface';
    }

    /**
     * @inheritdoc
     */
    public static function getFieldDefinitions(): array
    {
        return Craft::$app->getGql()->prepareFieldDefinitions(array_merge(parent::getFieldDefinitions(), [

        ]), self::getName());
    }
}
