<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\shopify\linktypes;

use craft\fields\linktypes\BaseElementLinkType;
use craft\shopify\elements\Product as ProductElement;

/**
 * Shopify Product link type.
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 5.3.0
 */
class Product extends BaseElementLinkType
{
    /**
     * @inheritdoc
     */
    protected static function elementType(): string
    {
        return ProductElement::class;
    }

    /**
     * @inheritdoc
     */
    public static function displayName(): string
    {
        return ProductElement::lowerDisplayName();
    }
}
