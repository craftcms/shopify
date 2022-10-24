<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\shopify\fields;

use Craft;
use craft\fields\BaseRelationField;
use craft\shopify\elements\Product;

/**
 * Class Shopify Product Field
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 3.0
 *
 * @property-read array $contentGqlType
 */
class Products extends BaseRelationField
{
    /**
     * @inheritdoc
     */
    public static function displayName(): string
    {
        return Craft::t('shopify', 'Shopify Products');
    }

    /**
     * @inheritdoc
     */
    public static function defaultSelectionLabel(): string
    {
        return Craft::t('shopify', 'Add a product');
    }

    /**
     * @inheritdoc
     */
    public static function elementType(): string
    {
        return Product::class;
    }
}
