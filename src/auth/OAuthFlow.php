<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\shopify\auth;

use craft\helpers\StringHelper;
use craft\shopify\Plugin;

/**
 * Handles the Shopify OAuth authorization flow.
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 8.0.0
 */
class OAuthFlow
{
    public const STATE_COOKIE_NAME = 'shopify_app_state';
    public const STATE_SIG_COOKIE_NAME = 'shopify_app_state_sig';
    public const ACCESS_TOKEN_POST_PATH = '/admin/oauth/access_token';

    /**
     * Begins the OAuth authorization flow.
     *
     * Sets two signed state cookies (via $setCookieFunction) and returns the Shopify authorization URL.
     *
     * @param callable $setCookieFunction fn(string $name, string $value, int $expire): bool
     */
    public static function begin(
        string $hostName,
        bool $isOnline,
        string $clientId,
        string $scopes,
        string $clientSecret,
        callable $setCookieFunction,
    ): string {
        $state = StringHelper::UUID();
        $sig = hash_hmac('sha256', $state, $clientSecret);
        $expire = time() + 60;

        $setCookieFunction(self::STATE_COOKIE_NAME, $state, $expire);
        $setCookieFunction(self::STATE_SIG_COOKIE_NAME, $sig, $expire);

        $redirectUri = Plugin::getInstance()->getSettings()->getAuthUrl();

        $query = http_build_query([
            'client_id' => $clientId,
            'scope' => $scopes,
            'redirect_uri' => $redirectUri,
            'state' => $state,
            'grant_options' => $isOnline ? ['per-user'] : [],
        ]);

        return "https://{$hostName}/admin/oauth/authorize?{$query}";
    }
}
