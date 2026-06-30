<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\shopify\tests\unit\elements;

use Codeception\Test\Unit;
use craft\shopify\collections\VariantCollection;
use craft\shopify\elements\Product;
use UnitTester;

/**
 * @group elements
 */
class ProductTest extends Unit
{
    public UnitTester $tester;

    private const PRODUCT_GID = 'gid://shopify/Product/7136060145715';
    private const PRODUCT_ID = 7136060145715;

    private function _makeProduct(string $shopifyStatus = Product::SHOPIFY_STATUS_ACTIVE): Product
    {
        $product = new Product();
        $product->shopifyGid = self::PRODUCT_GID;
        $product->shopifyId = self::PRODUCT_ID;
        $product->shopifyStatus = $shopifyStatus;
        return $product;
    }

    // -------------------------------------------------------------------------
    // shopifyId
    // -------------------------------------------------------------------------

    public function testShopifyIdIsNullByDefault(): void
    {
        $product = new Product();
        self::assertNull($product->shopifyId);
    }

    public function testShopifyIdIsInt(): void
    {
        $product = $this->_makeProduct();
        self::assertIsInt($product->shopifyId);
    }

    public function testShopifyIdCanBeSetOnNewElement(): void
    {
        $product = new Product();
        $product->shopifyId = self::PRODUCT_ID;
        self::assertEquals(self::PRODUCT_ID, $product->shopifyId);
    }

    // -------------------------------------------------------------------------
    // shopifyGid
    // -------------------------------------------------------------------------

    public function testShopifyGidIsNullByDefault(): void
    {
        $product = new Product();
        self::assertNull($product->shopifyGid);
    }

    public function testShopifyGidIsString(): void
    {
        $product = $this->_makeProduct();
        self::assertIsString($product->shopifyGid);
    }

    public function testShopifyGidHasCorrectFormat(): void
    {
        $product = $this->_makeProduct();
        self::assertStringStartsWith('gid://shopify/Product/', $product->shopifyGid);
    }

    public function testShopifyGidCanBeSetOnNewElement(): void
    {
        $product = new Product();
        $product->shopifyGid = self::PRODUCT_GID;
        self::assertEquals(self::PRODUCT_GID, $product->shopifyGid);
    }

    public function testShopifyGidNumericSegmentIsNumeric(): void
    {
        $product = $this->_makeProduct();
        $lastSegment = substr($product->shopifyGid, strrpos($product->shopifyGid, '/') + 1);
        self::assertMatchesRegularExpression('/^\d+$/', $lastSegment);
    }

    // -------------------------------------------------------------------------
    // shopifyId and shopifyGid relationship
    // -------------------------------------------------------------------------

    public function testShopifyIdMatchesNumericSegmentOfGid(): void
    {
        $product = $this->_makeProduct();
        $lastSegment = (int) substr($product->shopifyGid, strrpos($product->shopifyGid, '/') + 1);
        self::assertEquals($lastSegment, $product->shopifyId);
    }

    public function testShopifyGidContainsShopifyId(): void
    {
        $product = $this->_makeProduct();
        self::assertStringContainsString((string) $product->shopifyId, $product->shopifyGid);
    }

    // -------------------------------------------------------------------------
    // shopifyStatus
    // -------------------------------------------------------------------------

    public function testShopifyStatusDefaultsToActive(): void
    {
        $product = new Product();
        self::assertEquals(Product::SHOPIFY_STATUS_ACTIVE, $product->shopifyStatus);
    }

    public function testShopifyStatusActiveConstantValue(): void
    {
        self::assertEquals('active', Product::SHOPIFY_STATUS_ACTIVE);
    }

    public function testShopifyStatusDraftConstantValue(): void
    {
        self::assertEquals('draft', Product::SHOPIFY_STATUS_DRAFT);
    }

    public function testShopifyStatusArchivedConstantValue(): void
    {
        self::assertEquals('archived', Product::SHOPIFY_STATUS_ARCHIVED);
    }

    public function testShopifyStatusCanBeSetToDraft(): void
    {
        $product = $this->_makeProduct(Product::SHOPIFY_STATUS_DRAFT);
        self::assertEquals(Product::SHOPIFY_STATUS_DRAFT, $product->shopifyStatus);
    }

    public function testShopifyStatusCanBeSetToArchived(): void
    {
        $product = $this->_makeProduct(Product::SHOPIFY_STATUS_ARCHIVED);
        self::assertEquals(Product::SHOPIFY_STATUS_ARCHIVED, $product->shopifyStatus);
    }

    // -------------------------------------------------------------------------
    // getStatus()
    // -------------------------------------------------------------------------

    public function testGetStatusReturnsLiveWhenEnabledAndActive(): void
    {
        $product = $this->_makeProduct();
        $product->enabled = true;
        self::assertEquals(Product::STATUS_LIVE, $product->getStatus());
    }

    public function testGetStatusReturnsShopifyDraftWhenEnabledAndDraft(): void
    {
        $product = $this->_makeProduct(Product::SHOPIFY_STATUS_DRAFT);
        $product->enabled = true;
        self::assertEquals(Product::STATUS_SHOPIFY_DRAFT, $product->getStatus());
    }

    public function testGetStatusReturnsShopifyArchivedWhenEnabledAndArchived(): void
    {
        $product = $this->_makeProduct(Product::SHOPIFY_STATUS_ARCHIVED);
        $product->enabled = true;
        self::assertEquals(Product::STATUS_SHOPIFY_ARCHIVED, $product->getStatus());
    }

    public function testGetStatusReturnsDisabledWhenNotEnabled(): void
    {
        $product = $this->_makeProduct();
        $product->enabled = false;
        self::assertEquals(Product::STATUS_DISABLED, $product->getStatus());
    }

    public function testGetStatusReturnsDisabledRegardlessOfShopifyStatus(): void
    {
        foreach ([Product::SHOPIFY_STATUS_ACTIVE, Product::SHOPIFY_STATUS_DRAFT, Product::SHOPIFY_STATUS_ARCHIVED] as $shopifyStatus) {
            $product = $this->_makeProduct($shopifyStatus);
            $product->enabled = false;
            self::assertEquals(Product::STATUS_DISABLED, $product->getStatus(), "Expected disabled status for shopifyStatus=$shopifyStatus");
        }
    }

    // -------------------------------------------------------------------------
    // tags
    // -------------------------------------------------------------------------

    public function testGetTagsReturnsEmptyArrayByDefault(): void
    {
        $product = new Product();
        self::assertSame([], $product->getTags());
    }

    public function testSetTagsAcceptsArray(): void
    {
        $product = new Product();
        $product->setTags(['sale', 'new']);
        self::assertSame(['sale', 'new'], $product->getTags());
    }

    public function testSetTagsDecodesJsonString(): void
    {
        $product = new Product();
        $product->setTags('["sale","new"]');
        self::assertSame(['sale', 'new'], $product->getTags());
    }

    // -------------------------------------------------------------------------
    // options
    // -------------------------------------------------------------------------

    public function testGetOptionsReturnsEmptyArrayByDefault(): void
    {
        $product = new Product();
        self::assertSame([], $product->getOptions());
    }

    public function testSetOptionsAcceptsArray(): void
    {
        $product = new Product();
        $options = [['name' => 'Size', 'values' => ['S', 'M', 'L']]];
        $product->setOptions($options);
        self::assertSame($options, $product->getOptions());
    }

    public function testSetOptionsDecodesJsonString(): void
    {
        $product = new Product();
        $product->setOptions('[{"name":"Size","values":["S","M"]}]');
        self::assertEquals('Size', $product->getOptions()[0]['name']);
        self::assertSame(['S', 'M'], $product->getOptions()[0]['values']);
    }

    // -------------------------------------------------------------------------
    // data
    // -------------------------------------------------------------------------

    public function testGetDataReturnsEmptyArrayByDefault(): void
    {
        $product = new Product();
        self::assertSame([], $product->getData());
    }

    public function testSetDataAcceptsArray(): void
    {
        $product = new Product();
        $product->setData(['title' => 'Test Product']);
        self::assertSame(['title' => 'Test Product'], $product->getData());
    }

    public function testSetDataDecodesJsonString(): void
    {
        $product = new Product();
        $product->setData('{"title":"Test Product"}');
        self::assertEquals('Test Product', $product->getData()['title']);
    }

    public function testSetDataNullResultsInEmptyArray(): void
    {
        $product = new Product();
        $product->setData(['title' => 'Test Product']);
        $product->setData(null);
        self::assertSame([], $product->getData());
    }

    // -------------------------------------------------------------------------
    // descriptionHtml
    // -------------------------------------------------------------------------

    public function testGetDescriptionHtmlReturnsNullWhenNoData(): void
    {
        $product = new Product();
        self::assertNull($product->getDescriptionHtml());
    }

    public function testGetDescriptionHtmlReturnsValueFromData(): void
    {
        $product = new Product();
        $product->setData(['descriptionHtml' => '<p>Hello</p>']);
        self::assertEquals('<p>Hello</p>', $product->getDescriptionHtml());
    }

    // -------------------------------------------------------------------------
    // variants
    // -------------------------------------------------------------------------

    public function testGetVariantsReturnsEmptyCollectionWithNoShopifyGid(): void
    {
        $product = new Product();
        self::assertCount(0, $product->getVariants());
    }

    public function testSetVariantsWrapsPlainArrayInVariantCollection(): void
    {
        $product = $this->_makeProduct();
        $product->setVariants([]);
        self::assertInstanceOf(VariantCollection::class, $product->getVariants());
    }

    public function testSetVariantsKeepsExistingVariantCollection(): void
    {
        $product = $this->_makeProduct();
        $collection = VariantCollection::make();
        $product->setVariants($collection);
        self::assertSame($collection, $product->getVariants());
    }

    // -------------------------------------------------------------------------
    // images
    // -------------------------------------------------------------------------

    public function testGetImagesReturnsEmptyArrayWhenNoShopifyGid(): void
    {
        $product = new Product();
        self::assertSame([], $product->getImages());
    }

    public function testSetImagesAcceptsArray(): void
    {
        $product = $this->_makeProduct();
        $images = [['url' => 'https://example.com/img.jpg']];
        $product->setImages($images);
        self::assertSame($images, $product->getImages());
    }

    public function testSetImagesDecodesJsonString(): void
    {
        $product = $this->_makeProduct();
        $product->setImages('[{"url":"https://example.com/img.jpg"}]');
        self::assertIsArray($product->getImages());
        self::assertCount(1, $product->getImages());
    }

    // -------------------------------------------------------------------------
    // metafields
    // -------------------------------------------------------------------------

    public function testGetMetafieldsReturnsEmptyArrayWhenNoShopifyGid(): void
    {
        $product = new Product();
        self::assertSame([], $product->getMetafields());
    }

    public function testSetMetafieldsAcceptsListArray(): void
    {
        $product = $this->_makeProduct();
        $product->setMetafields([['key' => 'colour', 'value' => 'red']]);
        self::assertSame(['colour' => 'red'], $product->getMetafields());
    }

    public function testSetMetafieldsDecodesJsonListString(): void
    {
        $product = $this->_makeProduct();
        $product->setMetafields('[{"key":"colour","value":"red"}]');
        self::assertSame(['colour' => 'red'], $product->getMetafields());
    }

    public function testSetMetafieldsNormalizesRawListFormat(): void
    {
        $product = $this->_makeProduct();
        $product->setMetafields([['key' => 'colour', 'value' => 'red']]);
        self::assertSame(['colour' => 'red'], $product->getMetafields());
    }

    public function testSetMetafieldsThrowsForAssociativeArray(): void
    {
        $product = new Product();
        self::expectException(\InvalidArgumentException::class);
        $product->setMetafields(['colour' => 'red']);
    }

    public function testSetMetafieldsThrowsForJsonEncodedAssociativeArray(): void
    {
        $product = new Product();
        self::expectException(\InvalidArgumentException::class);
        $product->setMetafields('{"colour":"red"}');
    }

    // -------------------------------------------------------------------------
    // getCheapestVariant / getDefaultVariant
    // -------------------------------------------------------------------------

    public function testGetDefaultVariantReturnsNullWhenNoVariants(): void
    {
        $product = new Product();
        self::assertNull($product->getDefaultVariant());
    }

    public function testGetCheapestVariantReturnsNullWhenNoVariants(): void
    {
        $product = new Product();
        self::assertNull($product->getCheapestVariant());
    }
}
