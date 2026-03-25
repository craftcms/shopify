<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\shopify\gql\types;

use craft\errors\GqlException;
use craft\gql\base\SingularTypeInterface;
use craft\gql\GqlEntityRegistry;
use craft\helpers\Json;
use GraphQL\Language\AST\Node;
use GraphQL\Language\AST\NullValueNode;
use GraphQL\Language\AST\StringValueNode;
use GraphQL\Type\Definition\ScalarType;
use GraphQL\Type\Definition\Type;

/**
 * Class JsonType
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 7.1.0
 */
class JsonType extends ScalarType implements SingularTypeInterface
{
    /**
     * @return string
     */
    public static function getName(): string
    {
        return 'ShopifyJson';
    }

    public static function getType(): Type
    {
        return GqlEntityRegistry::getOrCreate(static::getName(), fn() => new self());
    }

    public function serialize($value)
    {
        if (empty($value)) {
            return null;
        }

        return $value;
    }

    public function parseValue($value)
    {
        if (!is_string($value) && !is_array($value) && !is_null($value)) {
            throw new GqlException('Data must be either a string, array, or null.');
        }

        return $value;
    }

    public function parseLiteral(Node $valueNode, ?array $variables = null)
    {
        if ($valueNode instanceof StringValueNode) {
            return Json::decodeIfJson($valueNode->value);
        }

        if ($valueNode instanceof NullValueNode) {
            return null;
        }

        // This message will be lost by the wrapping exception, but it feels good to provide one.
        throw new GqlException("Data must be either an array, string or null.");
    }
}
