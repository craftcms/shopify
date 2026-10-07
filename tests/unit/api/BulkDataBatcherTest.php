<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\shopify\tests\unit\api;

use Codeception\Test\Unit;
use craft\helpers\FileHelper;
use craft\shopify\api\BulkDataBatcher;
use UnitTester;

/**
 * @group api
 */
class BulkDataBatcherTest extends Unit
{
    public UnitTester $tester;

    private ?string $_filePath = null;

    protected function _after(): void
    {
        if ($this->_filePath !== null && file_exists($this->_filePath)) {
            FileHelper::unlink($this->_filePath);
        }
    }

    public function testReverseIsDisabledByDefault(): void
    {
        self::assertFalse((new BulkDataBatcher())->reverse);
    }

    public function testCountReturnsTotal(): void
    {
        $batcher = new BulkDataBatcher();
        $batcher->total = 42;

        self::assertSame(42, $batcher->count());
    }

    /**
     * @dataProvider batchSizeProvider
     */
    public function testForwardReadsEveryLineOnceInOrder(int $total, int $batchSize): void
    {
        $batcher = $this->_makeBatcher($total);

        self::assertSame($this->_lines($total), $this->_readAll($batcher, $batchSize));
    }

    /**
     * @dataProvider batchSizeProvider
     */
    public function testReverseReadsEveryLineOnceFromTheEnd(int $total, int $batchSize): void
    {
        $batcher = $this->_makeBatcher($total, reverse: true);

        self::assertSame(array_reverse($this->_lines($total)), $this->_readAll($batcher, $batchSize));
    }

    public static function batchSizeProvider(): array
    {
        return [
            'single batch' => [5, 10],
            'exact single batch' => [5, 5],
            'partial last batch' => [250, 100],
            'multiple of batch size' => [300, 100],
            'batch size of one' => [3, 1],
        ];
    }

    public function testForwardSliceStartsAtOffset(): void
    {
        $batcher = $this->_makeBatcher(6);

        self::assertSame(['line-2', 'line-3'], $this->_trimmed($batcher->getSlice(2, 2)));
    }

    public function testReverseSliceIsCountedFromTheEnd(): void
    {
        $batcher = $this->_makeBatcher(6, reverse: true);

        self::assertSame(['line-5', 'line-4'], $this->_trimmed($batcher->getSlice(0, 2)));
        self::assertSame(['line-3', 'line-2'], $this->_trimmed($batcher->getSlice(2, 2)));
        self::assertSame(['line-1', 'line-0'], $this->_trimmed($batcher->getSlice(4, 5)));
    }

    public function testReverseHandlesFileWithoutTrailingNewline(): void
    {
        $batcher = $this->_makeBatcher(4, reverse: true, trailingNewline: false);

        self::assertSame(['line-3', 'line-2', 'line-1', 'line-0'], $this->_readAll($batcher, 3));
    }

    public function testReverseThrowsWhenFileHasMoreLinesThanTotal(): void
    {
        $batcher = $this->_makeBatcher(5, reverse: true);
        $batcher->total = 4;

        $this->expectExceptionMessage('more lines than the expected total of 4');
        $batcher->getSlice(0, 2);
    }

    public function testReverseThrowsWhenFileHasFewerLinesThanTotal(): void
    {
        $batcher = $this->_makeBatcher(3, reverse: true);
        $batcher->total = 6;

        $this->expectExceptionMessage('fewer lines than the expected total of 6');
        $batcher->getSlice(0, 2);
    }

    public function testReverseThrowsPastTheStartOfTheFile(): void
    {
        $batcher = $this->_makeBatcher(3, reverse: true);

        $this->expectExceptionMessage('No more lines to read from the file.');
        $batcher->getSlice(3, 2);
    }

    public function testThrowsWhenFileIsMissing(): void
    {
        $batcher = new BulkDataBatcher();
        $batcher->filePath = '/path/that/does/not/exist.jsonl';
        $batcher->total = 1;

        $this->expectExceptionMessage('File not found');
        $batcher->getSlice(0, 1);
    }

    public function testThrowsWhenFileIsEmpty(): void
    {
        $this->_filePath = tempnam(sys_get_temp_dir(), 'shopify-batcher-');
        $batcher = new BulkDataBatcher();
        $batcher->filePath = $this->_filePath;
        $batcher->total = 1;
        $batcher->reverse = true;

        $this->expectExceptionMessage('File is empty');
        $batcher->getSlice(0, 1);
    }

    private function _makeBatcher(int $total, bool $reverse = false, bool $trailingNewline = true): BulkDataBatcher
    {
        $this->_filePath = tempnam(sys_get_temp_dir(), 'shopify-batcher-');
        file_put_contents($this->_filePath, implode("\n", $this->_lines($total)) . ($trailingNewline ? "\n" : ''));

        $batcher = new BulkDataBatcher();
        $batcher->filePath = $this->_filePath;
        $batcher->total = $total;
        $batcher->reverse = $reverse;

        return $batcher;
    }

    /**
     * Reads the file the same way `BaseBatchedJob` does, advancing the offset by each item returned.
     *
     * @return string[]
     */
    private function _readAll(BulkDataBatcher $batcher, int $batchSize): array
    {
        $lines = [];
        $offset = 0;

        while ($offset < $batcher->count()) {
            foreach ($batcher->getSlice($offset, $batchSize) as $line) {
                $offset++;
                if (trim($line) !== '') {
                    $lines[] = trim($line);
                }
            }
        }

        return $lines;
    }

    /**
     * @return string[]
     */
    private function _lines(int $total): array
    {
        return array_map(fn(int $i) => "line-$i", range(0, $total - 1));
    }

    /**
     * @return string[]
     */
    private function _trimmed(iterable $lines): array
    {
        return array_map('trim', collect($lines)->all());
    }
}
