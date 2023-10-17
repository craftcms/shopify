<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\shopify\helpers;

use craft\shopify\Plugin;

/**
 * Shopify API Helper.
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 3.3.0
 */
class Api
{
    /**
     * @return void
     */
    public static function rateLimit(): void
    {
        if (!Plugin::getInstance()->getSettings()->rateLimitRequests) {
            return;
        }

        sleep(Plugin::getInstance()->getSettings()->rateLimitSeconds);
    }
}
