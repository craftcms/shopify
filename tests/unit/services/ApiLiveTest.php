<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\shopify\tests\unit\services;

use Codeception\Test\Unit;
use craft\shopify\Plugin;
use Helper\RequiresLiveApi;
use Illuminate\Support\Collection;
use UnitTester;

/**
 * Exercises the real Shopify API, rather than fixtures.
 *
 * @see RequiresLiveApi
 * @group services
 * @group live
 */
class ApiLiveTest extends Unit
{
    use RequiresLiveApi;

    public UnitTester $tester;

    protected function _before(): void
    {
        $this->requireLiveApi();
    }

    public function testCanFetchShop(): void
    {
        $shop = Plugin::getInstance()->getApi()->getShop(true);

        self::assertIsArray($shop);
        self::assertArrayHasKey('name', $shop);
        self::assertArrayHasKey('myshopifyDomain', $shop);
        self::assertNotEmpty($shop['name']);
    }

    /**
     * Fetches the webhook subscriptions registered for this plugin's webhook URL. Read-only —
     * doesn't register or delete anything, so it's safe to run against a real store repeatedly.
     *
     * If the URL doesn't match any subscriptions registered in the store (e.g. a local test
     * environment's computed URL), this legitimately returns an empty collection — that's not
     * a failure on its own, just confirmation the call and response shape are correct.
     */
    public function testCanFetchWebhooks(): void
    {
        $webhooks = Plugin::getInstance()->getApi()->getWebhooks();

        self::assertInstanceOf(Collection::class, $webhooks);

        foreach ($webhooks as $webhook) {
            self::assertArrayHasKey('id', $webhook);
            self::assertArrayHasKey('topic', $webhook);
            self::assertArrayHasKey('uri', $webhook);
            self::assertStringStartsWith('gid://shopify/WebhookSubscription/', $webhook['id']);
        }
    }
}
