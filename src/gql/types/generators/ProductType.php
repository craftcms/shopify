<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\shopify\gql\types\generators;

use Craft;
use craft\gql\base\Generator;
use craft\gql\base\GeneratorInterface;
use craft\gql\base\ObjectType;
use craft\gql\base\SingleGeneratorInterface;
use craft\gql\GqlEntityRegistry;
use craft\shopify\elements\Product as ProductElement;
use craft\shopify\gql\interfaces\elements\Product as ProductInterface;
use craft\shopify\gql\types\elements\Product;

/**
 * Class ProductType
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 7.1.0
 */
class ProductType extends Generator implements GeneratorInterface, SingleGeneratorInterface
{
    /**
     * @inheritdoc
     */
    public static function generateTypes(mixed $context = null): array
    {
        $type = static::generateType($context);
        return [$type->name => $type];
    }

    /**
     * @inheritdoc
     */
    public static function generateType(mixed $context): ObjectType
    {
        return GqlEntityRegistry::getOrCreate(ProductElement::GQL_TYPE_NAME, fn() => new Product([
            'name' => ProductElement::GQL_TYPE_NAME,
            'fields' => function() use ($context) {
                $context ??= Craft::$app->getFields()->getLayoutByType(ProductElement::class);
                $contentFieldGqlTypes = self::getContentFields($context);
                $productFields = array_merge(ProductInterface::getFieldDefinitions(), $contentFieldGqlTypes);
                return Craft::$app->getGql()->prepareFieldDefinitions(
                    $productFields,
                    ProductElement::GQL_TYPE_NAME
                );
            },
        ]));
    }
}
