<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\shopify\tests\unit\twig;

use Codeception\Test\Unit;
use Craft;
use craft\shopify\elements\Product;
use craft\shopify\Plugin;
use craft\shopify\tests\fixtures\ShopifyDataFixture;
use craft\web\View;
use UnitTester;

/**
 * Renders the actual Twig templates shown in README.md, so that documented
 * `craft.shopifyProducts` examples are verified against real behavior instead
 * of going stale silently.
 *
 * `ProductQuery::beforePrepare()` inner-joins the `shopify_data` table (only
 * `shopify_products.shopifyId`/`shopifyGid` live on the element itself), so a
 * Product is only queryable once a matching `shopify_data` row exists for its
 * GID. `ShopifyDataFixture` already provides one, keyed by `PRODUCT_GID` below.
 *
 * @group twig
 */
class ProductQueryTemplatesTest extends Unit
{
    public UnitTester $tester;

    // Product GID from ShopifyDataFixture (see tests/fixtures/data/shopify-data.php)
    private const PRODUCT_GID = 'gid://shopify/Product/7136060145715';

    public function _fixtures(): array
    {
        return [
            'shopifyData' => ['class' => ShopifyDataFixture::class],
        ];
    }

    protected function _before(): void
    {
        parent::_before();

        Plugin::getInstance()->getProducts()->createOrUpdateProduct([
            'id' => self::PRODUCT_GID,
            'title' => 'TIMBERLAND | MENS 6 INCH PREMIUM BOOT',
            'descriptionHtml' => null,
            'createdAt' => '2026-01-01T00:00:00Z',
            'handle' => 'timberland-mens-6-inch-premium-boot',
            'options' => [],
            'productType' => null,
            'publishedAt' => '2026-01-01T00:00:00Z',
            'status' => 'ACTIVE',
            'tags' => ['egnition-sample-data', 'men', 'timberland', 'winter'],
            'templateSuffix' => null,
            'updatedAt' => '2026-01-01T00:00:00Z',
            'vendor' => 'TIMBERLAND',
        ]);
    }

    public function testShopifyGidQueryParamResolvesProductFromReadmeExample(): void
    {
        // Mirrors the README's `#### shopifyGid` example:
        // `.shopifyGid('gid://shopify/Product/123456789')`
        $output = Craft::$app->getView()->renderTemplate('shopify-gid-query', [
            'gid' => self::PRODUCT_GID,
        ], View::TEMPLATE_MODE_SITE);

        self::assertSame(self::PRODUCT_GID, trim($output));
    }

    public function testOptionsSearchParamResolvesProductFromReadmeExample(): void
    {
        // `options` is sourced entirely from the `shopify_data` join (see class
        // docblock) — createOrUpdateProduct() can't override it, so this relies
        // on the fixture's own "Color: yellow" option, set up in _before().

        // Search indexing only runs synchronously on console requests in real
        // usage (e.g. queue-driven syncs); our test module simulates a web
        // request, so it's queued instead. Index explicitly to match that.
        $product = Product::find()->shopifyGid(self::PRODUCT_GID)->status(null)->one();
        Craft::$app->getSearch()->indexElementAttributes($product);

        // Mirrors the README's `#### options` example: `.search('*yellow*')`
        $output = Craft::$app->getView()->renderTemplate('shopify-options-search', [], View::TEMPLATE_MODE_SITE);

        self::assertSame('1:' . self::PRODUCT_GID, trim($output));
    }
}
