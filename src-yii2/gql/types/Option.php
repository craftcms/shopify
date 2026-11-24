<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\shopify\gql\types;

use Craft;
use craft\gql\base\ObjectType;
use craft\gql\GqlEntityRegistry;
use GraphQL\Type\Definition\Type;

/**
 * Class Option
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 7.1.0
 */
class Option extends ObjectType
{
    /**
     * @return string
     */
    public static function getName(): string
    {
        return 'ShopifyOption';
    }

    public static function getType(): Type
    {
        if ($type = GqlEntityRegistry::getEntity(self::getName())) {
            return $type;
        }

        return GqlEntityRegistry::createEntity(self::getName(), new self([
            'name' => static::getName(),
            'fields' => self::class . '::getFieldDefinitions',
            'description' => '',
        ]));
    }

    public static function getFieldDefinitions(): array
    {
        return Craft::$app->getGql()->prepareFieldDefinitions([
            'id' => [
                'name' => 'id',
                'type' => Type::string(),
            ],
            'name' => [
                'name' => 'name',
                'type' => Type::string(),
            ],
            'position' => [
                'name' => 'position',
                'type' => Type::int(),
            ],
            'values' => [
                'name' => 'values',
                'type' => Type::listOf(Type::string()),
            ],
            'optionValues' => [
                'name' => 'optionValues',
                'type' => Type::listOf(GqlEntityRegistry::getOrCreate('ShopifyOptionOptionValue', fn() => new \GraphQL\Type\Definition\ObjectType([
                    'name' => 'ShopifyOptionOptionValue',
                    'fields' => [
                        'id' => [
                            'name' => 'id',
                            'type' => Type::string(),
                        ],
                        'name' => [
                            'name' => 'name',
                            'type' => Type::string(),
                        ],
                        'hasVariants' => [
                            'name' => 'hasVariants',
                            'type' => Type::boolean(),
                        ],
                    ],
                ]))),
            ],
        ], self::getName());
    }
}
