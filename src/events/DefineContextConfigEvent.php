<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\shopify\events;

use craft\base\Event;

/**
 * Event triggered before initializing the API context.
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 7.2.0
 */
class DefineContextConfigEvent extends Event
{
    /**
     * @var array Array of the arguments used to initialize the API context (`Context::initialize()`).
     */
    public array $config = [];
}
