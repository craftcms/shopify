<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\shopify\tests\unit\twig;

use Codeception\Test\Unit;
use Craft;
use craft\shopify\Plugin;
use craft\web\View;
use Helper\RequiresLiveApi;
use UnitTester;

/**
 * Renders the actual Twig template shown in the README's "API Service" section, so the
 * documented `craft.shopify.api.query()` recommendation is verified against real Shopify API
 * behavior instead of going stale silently.
 *
 * Read-only — queries `collections`, doesn't mutate anything in the store.
 *
 * @see RequiresLiveApi
 * @group twig
 * @group live
 */
class ApiQueryTemplateLiveTest extends Unit
{
    use RequiresLiveApi;

    public UnitTester $tester;

    protected function _before(): void
    {
        $this->requireLiveApi();
    }

    public function testCollectionsQueryTemplateFromReadmeExample(): void
    {
        // Mirrors the README's "API Service" example: `craft.shopify.api.query(gql)`
        // against `collections(first: 10) { nodes { id title } }`.
        $output = Craft::$app->getView()->renderTemplate('shopify-collections-query', [], View::TEMPLATE_MODE_SITE);

        self::assertMatchesRegularExpression('/^\d+$/', trim($output));

        // Confirm the shape of what the template consumed, by running the same query directly.
        $response = Plugin::getInstance()->getApi()->query('{ collections(first: 10) { nodes { id title } } }');
        $collections = $response['nodes'] ?? [];

        self::assertSame((string)count($collections), trim($output));

        foreach ($collections as $collection) {
            self::assertArrayHasKey('id', $collection);
            self::assertArrayHasKey('title', $collection);
            self::assertStringStartsWith('gid://shopify/Collection/', $collection['id']);
        }
    }
}
