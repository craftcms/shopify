<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\shopify\tests\unit\webhooks;

use Codeception\Test\Unit;
use craft\shopify\enums\WebhookTopics;
use craft\shopify\webhooks\WebhookRegistry;
use UnitTester;

/**
 * @group webhooks
 */
class WebhookRegistryTest extends Unit
{
    public UnitTester $tester;

    private const SECRET = 'whsec_test_1234';
    private const SHOP = 'my-shop.myshopify.com';

    // Precomputed: base64_encode(hash_hmac('sha256', '{"id":123,"title":"Test Product"}', 'whsec_test_1234', true))
    private const VALID_BODY = '{"id":123,"title":"Test Product"}';
    private const VALID_BODY_HMAC = '3hKzbZWAaO/tvYNwzucZIRaR6+YYGcnZoYXlcXv4W1M=';

    // Precomputed: base64_encode(hash_hmac('sha256', 'not-json-at-all', 'whsec_test_1234', true))
    private const INVALID_JSON_BODY = 'not-json-at-all';
    private const INVALID_JSON_BODY_HMAC = 'xARQubvcFrrJ6yFS+nOrrzNSwPIMUfglLSuvVImpqn8=';

    protected function _before(): void
    {
        // The registry is a static, process-wide map — reset it so tests don't leak handlers into each other.
        $this->_resetRegistry();
    }

    protected function _after(): void
    {
        $this->_resetRegistry();
    }

    // -------------------------------------------------------------------------
    // addHandler / getHandler
    // -------------------------------------------------------------------------

    public function testGetHandlerReturnsNullWhenNoneRegistered(): void
    {
        self::assertNull(WebhookRegistry::getHandler(WebhookTopics::ProductsCreate));
    }

    public function testAddHandlerIsRetrievableByGetHandler(): void
    {
        $handler = new class {
            public function handle(): void
            {
            }
        };

        WebhookRegistry::addHandler(WebhookTopics::ProductsCreate, $handler);

        self::assertSame($handler, WebhookRegistry::getHandler(WebhookTopics::ProductsCreate));
    }

    public function testAddHandlerOverwritesExistingHandlerForSameTopic(): void
    {
        $first = new class {
            public function handle(): void
            {
            }
        };
        $second = new class {
            public function handle(): void
            {
            }
        };

        WebhookRegistry::addHandler(WebhookTopics::ShopUpdate, $first);
        WebhookRegistry::addHandler(WebhookTopics::ShopUpdate, $second);

        self::assertSame($second, WebhookRegistry::getHandler(WebhookTopics::ShopUpdate));
    }

    public function testAddHandlerDoesNotAffectOtherTopics(): void
    {
        $handler = new class {
            public function handle(): void
            {
            }
        };

        WebhookRegistry::addHandler(WebhookTopics::ProductsCreate, $handler);

        self::assertNull(WebhookRegistry::getHandler(WebhookTopics::ProductsDelete));
    }

    // -------------------------------------------------------------------------
    // process — happy path
    // -------------------------------------------------------------------------

    public function testProcessDispatchesToRegisteredHandlerWithCorrectArguments(): void
    {
        $received = null;
        WebhookRegistry::addHandler(WebhookTopics::ProductsCreate, $this->_spyHandler(function(...$args) use (&$received) {
            $received = $args;
        }));

        WebhookRegistry::process(
            [
                'X-Shopify-Topic' => 'products/create',
                'X-Shopify-Shop-Domain' => self::SHOP,
                'X-Shopify-Hmac-SHA256' => self::VALID_BODY_HMAC,
            ],
            self::VALID_BODY,
            self::SECRET,
        );

        self::assertNotNull($received);
        [$topic, $shop, $body] = $received;
        self::assertSame(WebhookTopics::ProductsCreate, $topic);
        self::assertSame(self::SHOP, $shop);
        self::assertEquals(['id' => 123, 'title' => 'Test Product'], $body);
    }

    public function testProcessAcceptsLowercaseHeaderKeys(): void
    {
        $called = false;
        WebhookRegistry::addHandler(WebhookTopics::ProductsCreate, $this->_spyHandler(function() use (&$called) {
            $called = true;
        }));

        WebhookRegistry::process(
            [
                'x-shopify-topic' => 'products/create',
                'x-shopify-shop-domain' => self::SHOP,
                'x-shopify-hmac-sha256' => self::VALID_BODY_HMAC,
            ],
            self::VALID_BODY,
            self::SECRET,
        );

        self::assertTrue($called);
    }

    public function testProcessAcceptsArrayHeaderValues(): void
    {
        $called = false;
        WebhookRegistry::addHandler(WebhookTopics::ProductsCreate, $this->_spyHandler(function() use (&$called) {
            $called = true;
        }));

        WebhookRegistry::process(
            [
                'X-Shopify-Topic' => ['products/create'],
                'X-Shopify-Shop-Domain' => [self::SHOP],
                'X-Shopify-Hmac-SHA256' => [self::VALID_BODY_HMAC],
            ],
            self::VALID_BODY,
            self::SECRET,
        );

        self::assertTrue($called);
    }

    public function testProcessPassesEmptyArrayForNonJsonBody(): void
    {
        $received = null;
        WebhookRegistry::addHandler(WebhookTopics::ProductsCreate, $this->_spyHandler(function(...$args) use (&$received) {
            $received = $args;
        }));

        WebhookRegistry::process(
            [
                'X-Shopify-Topic' => 'products/create',
                'X-Shopify-Shop-Domain' => self::SHOP,
                'X-Shopify-Hmac-SHA256' => self::INVALID_JSON_BODY_HMAC,
            ],
            self::INVALID_JSON_BODY,
            self::SECRET,
        );

        self::assertEquals([], $received[2]);
    }

    // -------------------------------------------------------------------------
    // process — failure paths
    // -------------------------------------------------------------------------

    public function testProcessThrowsWhenTopicHeaderMissing(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Missing required Shopify webhook headers.');

        WebhookRegistry::process(
            [
                'X-Shopify-Shop-Domain' => self::SHOP,
                'X-Shopify-Hmac-SHA256' => self::VALID_BODY_HMAC,
            ],
            self::VALID_BODY,
            self::SECRET,
        );
    }

    public function testProcessThrowsWhenShopHeaderMissing(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Missing required Shopify webhook headers.');

        WebhookRegistry::process(
            [
                'X-Shopify-Topic' => 'products/create',
                'X-Shopify-Hmac-SHA256' => self::VALID_BODY_HMAC,
            ],
            self::VALID_BODY,
            self::SECRET,
        );
    }

    public function testProcessThrowsWhenHmacHeaderMissing(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Missing required Shopify webhook headers.');

        WebhookRegistry::process(
            [
                'X-Shopify-Topic' => 'products/create',
                'X-Shopify-Shop-Domain' => self::SHOP,
            ],
            self::VALID_BODY,
            self::SECRET,
        );
    }

    public function testProcessThrowsOnInvalidHmac(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Webhook HMAC validation failed.');

        WebhookRegistry::process(
            [
                'X-Shopify-Topic' => 'products/create',
                'X-Shopify-Shop-Domain' => self::SHOP,
                'X-Shopify-Hmac-SHA256' => 'not-a-real-signature',
            ],
            self::VALID_BODY,
            self::SECRET,
        );
    }

    public function testProcessThrowsOnInvalidHmacWhenBodyIsTamperedAfterSigning(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Webhook HMAC validation failed.');

        WebhookRegistry::process(
            [
                'X-Shopify-Topic' => 'products/create',
                'X-Shopify-Shop-Domain' => self::SHOP,
                'X-Shopify-Hmac-SHA256' => self::VALID_BODY_HMAC,
            ],
            '{"id":123,"title":"Tampered Product"}',
            self::SECRET,
        );
    }

    public function testProcessThrowsOnInvalidHmacWithWrongSecret(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Webhook HMAC validation failed.');

        WebhookRegistry::process(
            [
                'X-Shopify-Topic' => 'products/create',
                'X-Shopify-Shop-Domain' => self::SHOP,
                'X-Shopify-Hmac-SHA256' => self::VALID_BODY_HMAC,
            ],
            self::VALID_BODY,
            'a-completely-different-secret',
        );
    }

    public function testProcessThrowsOnUnknownTopic(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Unknown webhook topic: orders/create');

        WebhookRegistry::process(
            [
                'X-Shopify-Topic' => 'orders/create',
                'X-Shopify-Shop-Domain' => self::SHOP,
                'X-Shopify-Hmac-SHA256' => self::VALID_BODY_HMAC,
            ],
            self::VALID_BODY,
            self::SECRET,
        );
    }

    public function testProcessThrowsWhenNoHandlerRegisteredForTopic(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('No handler registered for webhook topic: products/create');

        // Deliberately not calling WebhookRegistry::addHandler() for this topic.
        WebhookRegistry::process(
            [
                'X-Shopify-Topic' => 'products/create',
                'X-Shopify-Shop-Domain' => self::SHOP,
                'X-Shopify-Hmac-SHA256' => self::VALID_BODY_HMAC,
            ],
            self::VALID_BODY,
            self::SECRET,
        );
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function _spyHandler(callable $onHandle): object
    {
        return new class($onHandle) {
            public function __construct(private $onHandle)
            {
            }

            public function handle(...$args): void
            {
                ($this->onHandle)(...$args);
            }
        };
    }

    private function _resetRegistry(): void
    {
        $property = new \ReflectionProperty(WebhookRegistry::class, 'registry');
        $property->setAccessible(true);
        $property->setValue(null, []);
    }
}
