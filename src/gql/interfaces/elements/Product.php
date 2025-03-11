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
use craft\gql\types\DateTime;
use craft\shopify\elements\Product as ProductElement;
use craft\shopify\gql\types\generators\ProductType;
use craft\shopify\gql\types\JsonType;
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
            'createdAt' => [
                'name' => 'createdAt',
                'type' => DateTime::getType(),
                'description' => 'The date the product was created in Shopify.',
            ],
            'publishedAt' => [
                'name' => 'publishedAt',
                'type' => DateTime::getType(),
                'description' => 'The date the product was published in Shopify.',
            ],
            'updatedAt' => [
                'name' => 'updatedAt',
                'type' => DateTime::getType(),
                'description' => 'The date the product was updated in Shopify.',
            ],
            'handle' => [
                'name' => 'handle',
                'type' => Type::string(),
                'description' => 'The product’s handle.',
            ],
            'descriptionHtml' => [
                'name' => 'descriptionHtml',
                'type' => Type::string(),
                'description' => 'The product’s description HTML in Shopify.',
            ],
            'productType' => [
                'name' => 'productType',
                'type' => Type::string(),
                'description' => 'The product’s type in Shopify.',
            ],
            'publishedOnCurrentPublication' => [
                'name' => 'publishedOnCurrentPublication',
                'type' => Type::boolean(),
                'description' => 'If the product is published on the current publication in Shopify.',
            ],
            'shopifyId' => [
                'name' => 'shopifyId',
                'type' => Type::string(),
                'description' => 'The product’s Shopify ID.',
            ],
            'shopifyGid' => [
                'name' => 'shopifyGid',
                'type' => Type::string(),
                'description' => 'The product’s Shopify GID.',
            ],
            'shopifyStatus' => [
                'name' => 'shopifyStatus',
                'type' => Type::string(),
                'description' => 'The product’s status in Shopify.',
            ],
            'vendor' => [
                'name' => 'vendor',
                'type' => Type::string(),
                'description' => 'The product’s vendor in Shopify.',
            ],
            'images' => [
                'name' => 'images',
                'type' => JsonType::getType(),
                'description' => 'The product’s images in Shopify.',
            ],
            'tags' => [
                'name' => 'tags',
                'type' => JsonType::getType(),
                'description' => 'The product’s tags in Shopify.',
            ],
            'metafields' => [
                'name' => 'metafields',
                'type' => JsonType::getType(),
                'description' => 'The product’s metafields in Shopify.',
            ],
            'variants' => [
                'name' => 'variants',
                'type' => JsonType::getType(),
                'description' => 'The product’s variants.',
            ],
        ]), self::getName());
    }
}
