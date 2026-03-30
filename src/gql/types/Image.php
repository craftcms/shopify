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
use GraphQL\Type\Definition\ResolveInfo;
use GraphQL\Type\Definition\Type;

/**
 * Class Image
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 7.1.0
 */
class Image extends ObjectType
{
    /**
     * @return string
     */
    public static function getName(): string
    {
        return 'ShopifyImage';
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
                'description' => 'Shopify GID of the image.',
            ],
            'alt' => [
                'name' => 'alt',
                'type' => Type::string(),
                'description' => 'Alt text of the image.',
            ],
            'mediaContentType' => [
                'name' => 'mediaContentType',
                'type' => Type::string(),
                'description' => 'Media content type of the image.',
            ],
            'createdAt' => [
                'name' => 'createdAt',
                'type' => Type::string(),
                'description' => 'Created date of the image.',
            ],
            'updatedAt' => [
                'name' => 'updatedAt',
                'type' => Type::string(),
                'description' => 'Updated date of the image.',
            ],
            'image' => [
                'name' => 'image',
                'type' => GqlEntityRegistry::getOrCreate('ShopifyImage::image', fn() => new \GraphQL\Type\Definition\ObjectType([
                    'name' => 'ShopifyImageImage',
                    'fields' => [
                        'url' => [
                            'name' => 'url',
                            'type' => Type::string(),
                            'description' => 'URL of the image.',
                        ],
                        'altText' => [
                            'name' => 'altText',
                            'type' => Type::string(),
                            'description' => 'Alt text of the image.',
                        ],
                        'height' => [
                            'name' => 'height',
                            'type' => Type::int(),
                            'description' => 'Height of the image.',
                        ],
                        'width' => [
                            'name' => 'width',
                            'type' => Type::int(),
                            'description' => 'Width of the image.',
                        ],
                    ],
                ])),
            ],
        ], self::getName());
    }

    protected function resolve(mixed $source, array $arguments, mixed $context, ResolveInfo $resolveInfo): mixed
    {
        $fieldName = $resolveInfo->fieldName;

        return match ($fieldName) {
            'url' => $source['image']['url'],
            default => parent::resolve($source, $arguments, $context, $resolveInfo),
        };
    }
}
