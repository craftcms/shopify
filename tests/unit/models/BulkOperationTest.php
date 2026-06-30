<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\shopify\tests\unit\models;

use Codeception\Test\Unit;
use craft\shopify\enums\BulkOperationStatus;
use craft\shopify\models\BulkOperation;
use craft\shopify\Plugin;
use craft\shopify\tests\fixtures\BulkOperationsFixture;
use UnitTester;

/**
 * @group models
 */
class BulkOperationTest extends Unit
{
    public UnitTester $tester;

    private const FIXTURE_GID = 'gid://shopify/BulkOperation/4848685842483';

    public function _fixtures(): array
    {
        return [
            'bulkOperations' => ['class' => BulkOperationsFixture::class],
        ];
    }

    private function _getFixtureOp(): BulkOperation
    {
        return Plugin::getInstance()->getBulkOperations()->getBulkOperationByShopifyGid(self::FIXTURE_GID);
    }

    // -------------------------------------------------------------------------
    // shopifyGid
    // -------------------------------------------------------------------------

    public function testShopifyGidIsString(): void
    {
        $op = $this->_getFixtureOp();
        self::assertIsString($op->shopifyGid);
    }

    public function testShopifyGidHasCorrectFormat(): void
    {
        $op = $this->_getFixtureOp();
        self::assertStringStartsWith('gid://shopify/BulkOperation/', $op->shopifyGid);
    }

    public function testShopifyGidMatchesFixture(): void
    {
        $op = $this->_getFixtureOp();
        self::assertEquals(self::FIXTURE_GID, $op->shopifyGid);
    }

    public function testShopifyGidNumericSegmentIsNumeric(): void
    {
        $op = $this->_getFixtureOp();
        $lastSegment = substr($op->shopifyGid, strrpos($op->shopifyGid, '/') + 1);
        self::assertMatchesRegularExpression('/^\d+$/', $lastSegment);
    }

    // -------------------------------------------------------------------------
    // shopifyGid set on new model
    // -------------------------------------------------------------------------

    public function testShopifyGidCanBeSetOnNewModel(): void
    {
        $model = new BulkOperation();
        $model->shopifyGid = self::FIXTURE_GID;

        self::assertEquals(self::FIXTURE_GID, $model->shopifyGid);
        self::assertIsString($model->shopifyGid);
    }

    public function testShopifyGidIsNullByDefault(): void
    {
        $model = new BulkOperation();
        self::assertNull($model->shopifyGid);
    }
}
