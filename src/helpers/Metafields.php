<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\shopify\helpers;

use craft\helpers\Json;
use Shopify\Rest\Admin2022_10\Metafield as ShopifyMetafield;

class Metafields
{
    /**
     * @var array Data types that should be expanded into an array.
     * @see https://shopify.dev/apps/metafields/types
     */
    public const JSON_TYPES = [
        'dimension',
        'json',
        'money',
        'rating',
        'volume',
    ];

    /**
     * @var string Prefix given to metafield types that are configured to accept multiple values and will be encoded as JSON.
     * @see https://shopify.dev/apps/metafields/types#list-types
     */
    public const LIST_PREFIX = 'list';

    /**
     * Unpacks metadata from the Shopify API.
     * 
     * @param ShopifyMetafield[] $fields
     * @return array
     */
    public static function unpack(array $fields): array
    {
        $data = [];

        foreach ($fields as $field) {
            $data[$field->key] = static::decode($field);
        }

        return $data;
    }

    /**
     * Turn a metafield API resource into a simple value, based on its type.
     */
    public static function decode(ShopifyMetafield $field)
    {
        $value = $field->value;

        if (in_array($field->type, static::JSON_TYPES) || strpos($field->type, static::LIST_PREFIX) === 0) {
            $value = Json::decodeIfJson($value);
        }

        return $value;
    }
}
