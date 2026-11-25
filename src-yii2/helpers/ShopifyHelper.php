<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\shopify\helpers;

/**
 * Shopify helper methods.
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 8.0.0
 */
class ShopifyHelper
{
    /**
     * Validates and normalizes a Shopify shop domain.
     *
     * Returns the sanitized domain string, or null if the value is not a valid Shopify domain.
     */
    public static function sanitizeShopDomain(string $shop): ?string
    {
        $shop = strtolower(trim($shop));

        // Strip protocol prefix
        $shop = preg_replace('#^https?://#', '', $shop);

        // If no dot, assume it's a myshopify subdomain
        if (!str_contains($shop, '.')) {
            $shop .= '.myshopify.com';
        }

        if (!preg_match('/^[a-zA-Z0-9][a-zA-Z0-9\-]*\.(myshopify\.com|myshopify\.io)$/', $shop)) {
            return null;
        }

        return $shop;
    }

    /**
     * Validates the HMAC signature on a set of Shopify query parameters.
     */
    public static function validateHmac(array $params, string $secret): bool
    {
        if (!isset($params['hmac'])) {
            return false;
        }

        $hmac = $params['hmac'];
        $computed = hash_hmac('sha256', self::_buildQueryString($params), $secret);

        return hash_equals($computed, $hmac);
    }

    private static function _buildQueryString(array $params): string
    {
        unset($params['hmac']);
        ksort($params);

        $pairs = [];
        foreach ($params as $key => $value) {
            if (is_array($value)) {
                $value = '["' . implode('","', $value) . '"]';
            }
            $pairs[] = urlencode((string)$key) . '=' . urlencode((string)$value);
        }

        return implode('&', $pairs);
    }
}
