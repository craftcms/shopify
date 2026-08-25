<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\shopify\enums;

/**
 * Shopify Admin API version enum.
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 8.0.0
 */
enum ApiVersion: string
{
    case January2026 = '2026-01';
    case April2026 = '2026-04';
    case July2026 = '2026-07';
}
