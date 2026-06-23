<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\shopify\tests\unit\models;

use Codeception\Test\Unit;
use craft\shopify\collections\VariantCollection;
use craft\shopify\Plugin;
use craft\shopify\tests\fixtures\ShopifyDataFixture;
use UnitTester;

/**
 * @group models
 */
class VariantTest extends Unit
{
    public UnitTester $tester;

    private const PRODUCT_GID = 'gid://shopify/Product/7136060145715';
    private const VARIANT_GID = 'gid://shopify/ProductVariant/41966390083635';
    private const VARIANT_ID = '41966390083635';

    public function _fixtures(): array
    {
        return [
            'shopifyData' => ['class' => ShopifyDataFixture::class],
        ];
    }

    private function _getFirstVariant(): \craft\shopify\models\Variant
    {
        $products = $this->_makeMockProduct(self::PRODUCT_GID);
        Plugin::getInstance()->getProducts()->eagerLoadVariantsForProducts([$products]);
        return $products->variants->first();
    }

    // -------------------------------------------------------------------------
    // shopifyGid
    // -------------------------------------------------------------------------

    public function testShopifyGidIsString(): void
    {
        $variant = $this->_getFirstVariant();
        self::assertIsString($variant->shopifyGid);
    }

    public function testShopifyGidHasCorrectFormat(): void
    {
        $variant = $this->_getFirstVariant();
        self::assertStringStartsWith('gid://shopify/ProductVariant/', $variant->shopifyGid);
    }

    public function testShopifyGidMatchesFixture(): void
    {
        $variant = $this->_getFirstVariant();
        self::assertEquals(self::VARIANT_GID, $variant->shopifyGid);
    }

    // -------------------------------------------------------------------------
    // shopifyId
    // -------------------------------------------------------------------------

    public function testShopifyIdIsString(): void
    {
        $variant = $this->_getFirstVariant();
        self::assertIsString($variant->shopifyId);
    }

    public function testShopifyIdIsNumeric(): void
    {
        $variant = $this->_getFirstVariant();
        self::assertMatchesRegularExpression('/^\d+$/', $variant->shopifyId);
    }

    public function testShopifyIdMatchesFixture(): void
    {
        $variant = $this->_getFirstVariant();
        self::assertEquals(self::VARIANT_ID, $variant->shopifyId);
    }

    // -------------------------------------------------------------------------
    // shopifyId and shopifyGid relationship
    // -------------------------------------------------------------------------

    public function testShopifyIdIsLastSegmentOfGid(): void
    {
        $variant = $this->_getFirstVariant();
        $lastSegment = substr($variant->shopifyGid, strrpos($variant->shopifyGid, '/') + 1);
        self::assertEquals($lastSegment, $variant->shopifyId);
    }

    public function testShopifyGidContainsShopifyId(): void
    {
        $variant = $this->_getFirstVariant();
        self::assertStringContainsString($variant->shopifyId, $variant->shopifyGid);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function _makeMockProduct(string $gid): object
    {
        return new class($gid) {
            public string $shopifyGid;
            public ?VariantCollection $variants = null;

            public function __construct(string $gid)
            {
                $this->shopifyGid = $gid;
            }

            public function setVariants(VariantCollection $variants): void
            {
                $this->variants = $variants;
            }
        };
    }
}
