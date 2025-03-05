<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\shopify\gql\resolvers\elements;

use craft\shopify\elements\Product as ProductElement;
use craft\helpers\Gql as GqlHelper;
use craft\elements\db\ElementQuery;
use craft\gql\base\ElementResolver;

/**
 * Class Product
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 6.1.0
 */
class Product extends ElementResolver
{
    /**
     * @inheritdoc
     */
    public static function prepareQuery(mixed $source, array $arguments, $fieldName = null): mixed
    {
        // If this is the beginning of a resolver chain, start fresh
        if ($source === null) {
            $query = ProductElement::find();
        // If not, get the prepared element query
        } else {
            $query = $source->$fieldName;
        }

        // If it's preloaded, it's preloaded.
        if (!$query instanceof ElementQuery) {
            return $query;
        }

        foreach ($arguments as $key => $value) {
            if (method_exists($query, $key)) {
                $query->$key($value);
            } elseif (property_exists($query, $key)) {
                $query->$key = $value;
            } else {
                // Catch custom field queries
                $query->$key($value);
            }
        }

        $pairs = GqlHelper::extractAllowedEntitiesFromSchema();
        $typeName = (new ProductElement())->getGqlTypeName();

        if (!isset($pairs[$typeName])) {
            return [];
        }

        return $query;
    }
}
