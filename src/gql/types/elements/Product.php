<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\shopify\gql\types\elements;

use craft\gql\types\elements\Element as ElementType;
use craft\shopify\gql\interfaces\elements\Product as ProductInterface;
use GraphQL\Type\Definition\ResolveInfo;

/**
 * Class Product
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 7.1.0
 */
class Product extends ElementType
{
    /**
     * @inheritdoc
     */
    public function __construct(array $config)
    {
        $config['interfaces'] = [
            ProductInterface::getType(),
        ];

        parent::__construct($config);
    }

    protected function resolve(mixed $source, array $arguments, mixed $context, ResolveInfo $resolveInfo): mixed
    {
        $fieldName = $resolveInfo->fieldName;
        return match ($fieldName) {
            // @TODO remove this when the conflict in (https://github.com/craftcms/cms/blob/7b889521442ef68be39edf52b55f4747da722e94/src/gql/ElementQueryConditionBuilder.php#L290-L321) is resolved
            'variants' => $source->getVariants(),
            // Remap metafields to be an array of key/value pairs instead of an associative array
            'metafields' => collect($source->getMetafields())
                ->map(fn($value, $key) => ['key' => $key, 'value' => $value])
                ->all(),
            default => parent::resolve($source, $arguments, $context, $resolveInfo),
        };
    }
}
