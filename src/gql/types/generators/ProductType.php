<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\shopify\gql\types\generators;

use Craft;
use craft\base\Field;
use craft\shopify\elements\Product as ProductElement;
use craft\shopify\gql\interfaces\elements\Product as ProductInterface;
use craft\shopify\gql\types\elements\Product as ProductTypeElement;
use craft\gql\base\GeneratorInterface;
use craft\gql\GqlEntityRegistry;
use craft\shopify\Plugin;

/**
 * Class ProductType
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 6.1.0
 */
class ProductType implements GeneratorInterface
{
    /**
     * @inheritdoc
     */
    public static function generateTypes(mixed $context = null): array
    {
        $productFieldLayout = Plugin::getInstance()->getSettings()->getProductFieldLayout();

        $typeName = (new ProductElement())->getGqlTypeName();
        $contentFields = $productFieldLayout->getCustomFields();
        $contentFieldGqlTypes = [];

        /** @var Field $contentField */
        foreach ($contentFields as $contentField) {
            $contentFieldGqlTypes[$contentField->handle] = $contentField->getContentGqlType();
        }

        $productFields = Craft::$app->getGql()->prepareFieldDefinitions(array_merge(ProductInterface::getFieldDefinitions(), $contentFieldGqlTypes), $typeName);
        return [
            $typeName => GqlEntityRegistry::getEntity($typeName) ?: GqlEntityRegistry::createEntity($typeName, new ProductTypeElement([
                'name' => $typeName,
                'fields' => function() use ($productFields) {
                    return $productFields;
                },
            ]))
        ];
    }
}
