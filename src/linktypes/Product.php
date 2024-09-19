<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\shopify\linktypes;

use craft\shopify\elements\Product as ProductElement;
use craft\fields\linktypes\BaseElementLinkType;


/**
 * Shopify Product link type.
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 5.2.0
 */
class Product extends BaseElementLinkType
{
    protected static function elementType(): string
    {
        return ProductElement::class;
    }

    public static function displayName(): string
    {
        return ProductElement::lowerDisplayName();
    }

}
