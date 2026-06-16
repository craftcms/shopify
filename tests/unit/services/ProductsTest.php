<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\shopify\tests\unit\services;

use Codeception\Test\Unit;
use craft\shopify\collections\VariantCollection;
use craft\shopify\db\Table;
use craft\shopify\Plugin;
use craft\shopify\records\ShopifyData;
use craft\shopify\tests\fixtures\ShopifyDataFixture;
use UnitTester;

/**
 * @group services
 */
class ProductsTest extends Unit
{
    public UnitTester $tester;

    // Product GID from the fixture with known variants and images
    private const PRODUCT_GID = 'gid://shopify/Product/7136060145715';

    public function _fixtures(): array
    {
        return [
            'shopifyData' => ['class' => ShopifyDataFixture::class],
        ];
    }

    // -------------------------------------------------------------------------
    // normalizeShopifyGid
    // -------------------------------------------------------------------------

    public function testNormalizeShopifyGidPassesThroughFullGid(): void
    {
        $gid = 'gid://shopify/Product/12345';
        $result = Plugin::getInstance()->getProducts()->normalizeShopifyGid($gid);
        self::assertEquals($gid, $result);
    }

    public function testNormalizeShopifyGidPrefixesNumericId(): void
    {
        $result = Plugin::getInstance()->getProducts()->normalizeShopifyGid('12345');
        self::assertEquals('gid://shopify/Product/12345', $result);
    }

    public function testNormalizeShopifyGidRespectsCustomType(): void
    {
        $result = Plugin::getInstance()->getProducts()->normalizeShopifyGid('99', 'InventoryItem');
        self::assertEquals('gid://shopify/InventoryItem/99', $result);
    }

    public function testNormalizeShopifyGidDoesNotDoublePrefix(): void
    {
        $gid = 'gid://shopify/InventoryItem/99';
        $result = Plugin::getInstance()->getProducts()->normalizeShopifyGid($gid, 'InventoryItem');
        self::assertEquals($gid, $result);
    }

    // -------------------------------------------------------------------------
    // deleteShopifyDataByShopifyId
    // -------------------------------------------------------------------------

    public function testDeleteShopifyDataByShopifyIdRemovesProductAndChildren(): void
    {
        // Verify fixture data exists before deletion
        $productRow = ShopifyData::find()->where(['shopifyId' => self::PRODUCT_GID, 'type' => 'Product'])->one();
        self::assertNotNull($productRow, 'Fixture product row must exist before deletion test.');

        $variantsBefore = ShopifyData::find()
            ->where(['parentId' => self::PRODUCT_GID, 'type' => 'ProductVariant'])
            ->count();
        self::assertGreaterThan(0, $variantsBefore, 'Fixture must have at least one variant.');

        Plugin::getInstance()->getProducts()->deleteShopifyDataByShopifyId(self::PRODUCT_GID);

        // Product row should be gone
        $productRow = ShopifyData::find()->where(['shopifyId' => self::PRODUCT_GID, 'type' => 'Product'])->one();
        self::assertNull($productRow);

        // All direct children (variants, images) should also be gone
        $children = ShopifyData::find()->where(['parentId' => self::PRODUCT_GID])->count();
        self::assertEquals(0, $children);
    }

    public function testDeleteShopifyDataByShopifyIdAcceptsNumericId(): void
    {
        // Numeric ID should be normalized to GID before lookup — no exception expected
        Plugin::getInstance()->getProducts()->deleteShopifyDataByShopifyId('7136060145715');
        $this->assertTrue(true);
    }

    // -------------------------------------------------------------------------
    // eagerLoadVariantsForProducts
    // -------------------------------------------------------------------------

    public function testEagerLoadVariantsForProductsSetsVariantsOnEachProduct(): void
    {
        $products = $this->_makeMockProducts([
            self::PRODUCT_GID,
            'gid://shopify/Product/7136060964915',
        ]);

        Plugin::getInstance()->getProducts()->eagerLoadVariantsForProducts($products);

        foreach ($products as $product) {
            self::assertNotNull($product->variants, "Variants not set for {$product->shopifyGid}");
            self::assertInstanceOf(VariantCollection::class, $product->variants);
            self::assertGreaterThan(0, $product->variants->count(), "Variant collection is empty for {$product->shopifyGid}");
        }
    }

    public function testEagerLoadVariantsForProductsVariantsHaveCorrectParent(): void
    {
        $products = $this->_makeMockProducts([self::PRODUCT_GID]);

        Plugin::getInstance()->getProducts()->eagerLoadVariantsForProducts($products);

        $variants = $products[0]->variants;
        foreach ($variants as $variant) {
            self::assertEquals(self::PRODUCT_GID, $variant->parentId);
        }
    }

    // -------------------------------------------------------------------------
    // eagerLoadImagesForProducts
    // -------------------------------------------------------------------------

    public function testEagerLoadImagesForProductsSetsImagesOnEachProduct(): void
    {
        $products = $this->_makeMockProducts([self::PRODUCT_GID]);

        Plugin::getInstance()->getProducts()->eagerLoadImagesForProducts($products);

        self::assertNotNull($products[0]->images);
        self::assertIsArray($products[0]->images);
        self::assertGreaterThan(0, count($products[0]->images));
    }

    // -------------------------------------------------------------------------
    // eagerLoadMetafieldsForProducts
    // -------------------------------------------------------------------------

    public function testEagerLoadMetafieldsForProductsWithNoMetafieldsReturnsEmptyArray(): void
    {
        // Fixture data has no Metafield rows; method should not crash and should
        // leave metafields empty.
        $products = $this->_makeMockProducts([self::PRODUCT_GID]);

        Plugin::getInstance()->getProducts()->eagerLoadMetafieldsForProducts($products);

        self::assertIsArray($products[0]->metafields);
        self::assertEmpty($products[0]->metafields);
    }

    public function testEagerLoadMetafieldsForProductsMapsKeyValuePairs(): void
    {
        $productGid = 'gid://shopify/Product/metafield-test-product';

        // Insert a product row and a metafield child
        \Yii::$app->db->createCommand()->insert(Table::DATA, [
            'shopifyId' => $productGid,
            'type' => 'Product',
            'data' => json_encode(['id' => $productGid, 'title' => 'Test']),
            'parentId' => null,
            'uid' => 'test-product-uid-for-metafields',
            'dateCreated' => date('Y-m-d H:i:s'),
            'dateUpdated' => date('Y-m-d H:i:s'),
        ])->execute();

        \Yii::$app->db->createCommand()->insert(Table::DATA, [
            'shopifyId' => 'gid://shopify/Metafield/test-mf-1',
            'type' => 'Metafield',
            'data' => json_encode(['id' => 'gid://shopify/Metafield/test-mf-1', 'key' => 'my_key', 'value' => 'my_value']),
            'parentId' => $productGid,
            'uid' => 'test-metafield-uid-001',
            'dateCreated' => date('Y-m-d H:i:s'),
            'dateUpdated' => date('Y-m-d H:i:s'),
        ])->execute();

        $products = $this->_makeMockProducts([$productGid]);
        Plugin::getInstance()->getProducts()->eagerLoadMetafieldsForProducts($products);

        self::assertArrayHasKey('my_key', $products[0]->metafields);
        self::assertEquals('my_value', $products[0]->metafields['my_key']);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * Creates anonymous mock Product objects with the required interface for
     * eager loading methods without needing a full Craft element.
     */
    private function _makeMockProducts(array $shopifyGids): array
    {
        return array_map(function(string $gid) {
            return new class($gid) {
                public string $shopifyGid;
                public ?VariantCollection $variants = null;
                public ?array $images = null;
                public array $metafields = [];

                public function __construct(string $gid)
                {
                    $this->shopifyGid = $gid;
                }

                public function setVariants(VariantCollection $variants): void
                {
                    $this->variants = $variants;
                }

                public function setImages(array $images): void
                {
                    $this->images = $images;
                }

                public function setMetafields(array $metafields): void
                {
                    $this->metafields = $metafields;
                }
            };
        }, $shopifyGids);
    }
}
