<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\shopify\exceptions;

/**
 * Exception thrown when a Shopify API request fails due to a transport-level (communication) failure,
 * as opposed to a GraphQL-level error or mutation `userErrors`.
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 8.0.0
 */
class ShopifyApiCommunicationException extends ShopifyApiException
{
}
