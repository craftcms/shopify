<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\shopify\events;

use craft\base\Event;

/**
 * Event triggered while resolving fields that should be requested from Shopify.
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 7.0.0
 */
class DefineGqlFieldsEvent extends Event
{
    /**
     * @var array
     */
    public array $fields;
}
