<?php

namespace Helper;

use craft\helpers\App;
use craft\shopify\Plugin;

/**
 * Shared setup for tests that exercise the real Shopify API rather than fixtures.
 *
 * Tests using this trait are skipped unless `SHOPIFY_LIVE_HOST_NAME`, `SHOPIFY_LIVE_CLIENT_ID`,
 * `SHOPIFY_LIVE_CLIENT_SECRET`, and `SHOPIFY_LIVE_ACCESS_TOKEN` are all present (e.g. via `tests/.env`,
 * which is gitignored). This is what makes them skip automatically in CI, which has no access to
 * real credentials.
 *
 * `SHOPIFY_LIVE_ACCESS_TOKEN` must come from an app that has already completed the plugin's OAuth
 * authorization flow (see the README's "Connect to Shopify" section) — Shopify only issues access
 * tokens after a real authorization, so this can't be an arbitrary string.
 *
 * `SHOPIFY_LIVE_API_VERSION` is optional; if unset, the plugin's default API version is used. Set it
 * to pin live tests to a specific version — e.g. to check the plugin against a version Shopify has
 * announced but that isn't the default yet, or one that's approaching sunset.
 *
 * Point these credentials at a Shopify Partner development store, not a production one.
 *
 * Tests using this trait should be read-only. Avoid anything that mutates real store state
 * (registering/deleting webhooks, running bulk operations, etc.) — there's no teardown here to
 * revert changes made through Shopify's API itself (only the local test DB transaction rolls
 * back), so a mutating test would leave residue in the store or need its own cleanup logic.
 */
trait RequiresLiveApi
{
    /**
     * Configures the plugin with live credentials, or skips the current test if they aren't present.
     */
    protected function requireLiveApi(): void
    {
        $hostName = App::env('SHOPIFY_LIVE_HOST_NAME');
        $clientId = App::env('SHOPIFY_LIVE_CLIENT_ID');
        $clientSecret = App::env('SHOPIFY_LIVE_CLIENT_SECRET');
        $accessToken = App::env('SHOPIFY_LIVE_ACCESS_TOKEN');
        $apiVersion = App::env('SHOPIFY_LIVE_API_VERSION');

        if (!$hostName || !$clientId || !$clientSecret || !$accessToken) {
            self::markTestSkipped('Set SHOPIFY_LIVE_HOST_NAME, SHOPIFY_LIVE_CLIENT_ID, SHOPIFY_LIVE_CLIENT_SECRET, and SHOPIFY_LIVE_ACCESS_TOKEN in tests/.env to run live Shopify API tests.');
        }

        $settings = Plugin::getInstance()->getSettings();
        $settings->setHostName($hostName);
        $settings->setClientId($clientId);
        $settings->setClientSecret($clientSecret);
        $settings->setAccessToken($accessToken);

        if ($apiVersion) {
            $supportedVersions = Plugin::getInstance()->getApi()->getSupportedApiVersions();
            if (!in_array($apiVersion, $supportedVersions, true)) {
                self::fail(sprintf(
                    'SHOPIFY_LIVE_API_VERSION "%s" is not one of the versions this plugin supports (%s).',
                    $apiVersion,
                    implode(', ', $supportedVersions),
                ));
            }

            $settings->setApiVersion($apiVersion);
        }
    }
}
