<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\shopify\tests\unit\enums;

use Codeception\Test\Unit;
use craft\shopify\enums\BulkOperationStatus;
use UnitTester;

/**
 * @group enums
 */
class BulkOperationStatusTest extends Unit
{
    public UnitTester $tester;

    /**
     * `statusAsLabel()` and `statusLabelHtml()` both `match()` over every case with no `default`
     * arm in at least one branch, so an enum case added without a matching label/color throws
     * `\UnhandledMatchError` at render time rather than failing a test. Exercising every case
     * here turns that into a caught regression instead of a production error.
     */
    public function testEveryCaseHasALabel(): void
    {
        foreach (BulkOperationStatus::cases() as $case) {
            self::assertNotSame('', $case->statusAsLabel(), "{$case->name} has no label");
        }
    }

    public function testEveryCaseRendersAStatusLabel(): void
    {
        foreach (BulkOperationStatus::cases() as $case) {
            self::assertIsString($case->statusLabelHtml());
            self::assertNotSame('', $case->statusLabelHtml());
        }
    }

    public function testFailedCaseExists(): void
    {
        $case = BulkOperationStatus::tryFrom('failed');

        self::assertSame(BulkOperationStatus::Failed, $case);
        self::assertSame('Failed', $case->statusAsLabel());
    }
}
