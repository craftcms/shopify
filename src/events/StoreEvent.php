<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\shopify\events;

use craft\shopify\models\Store;
use yii\base\Event;

/**
 * Class StoreEvent
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 3.1
 */
class StoreEvent extends Event
{
    /**
     * @var Store The store
     */
    public Store $store;

    /**
     * @var bool Whether the store is brand new.
     */
    public bool $isNew = false;
}
