<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\shopify\events;

use craft\events\CancelableEvent;
use craft\shopify\elements\Product as ProductElement;

/**
 * Event triggered just before a synchronized product element is going to be saved.
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 */
class ShopifyProductSyncEvent extends CancelableEvent
{
    /**
     * @var ProductElement Craft product element being synchronized.
     */
    public ProductElement $element;

    /**
     * @var array Source Shopify API resource.
     */
    public array $source;
}
