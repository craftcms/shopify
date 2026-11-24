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
 * Class Metafield
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 7.1.0
 */
class Metafield extends ObjectType
{
    /**
     * @return string
     */
    public static function getName(): string
    {
        return 'ShopifyMetafield';
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
            'key' => [
                'name' => 'key',
                'type' => Type::string(),
                'description' => 'The key of the metafield.',
            ],
            'value' => [
                'name' => 'value',
                'type' => Type::string(),
                'description' => 'The value of the metafield.',
            ],
        ], self::getName());
    }
}
