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

    /**
     * @dataProvider batchSizeProvider
     */
    public function testReverseReadsEveryLineOnceFromStoredPositions(int $total, int $batchSize): void
    {
        $batcher = $this->_makeBatcher($total, reverse: true);

        self::assertSame(array_reverse($this->_lines($total)), $this->_readAll($batcher, $batchSize, usePositions: true));
    }

    public function testReverseReadsLinesAcrossChunkBoundaries(): void
    {
        $lines = [];
        for ($i = 0; $i < 300; $i++) {
            $lines[] = "line-$i-" . str_repeat('x', $i * 37 % 1000);
        }
        $lines[150] = 'line-150-' . str_repeat('y', 200000);
        $batcher = $this->_makeBatcherFromContents(implode("\n", $lines) . "\n", count($lines));

        self::assertSame(array_reverse($lines), $this->_readAll($batcher, 7, usePositions: true));
    }

    public function testReverseSkipsBlankLines(): void
    {
        $batcher = $this->_makeBatcherFromContents("line-0\n\nline-1\n  \nline-2\n\n", 3);

        self::assertSame(['line-2', 'line-1', 'line-0'], $this->_readAll($batcher, 2, usePositions: true));
    }

    public function testReversePositionAfterPartiallyProcessedSlice(): void
    {
        $batcher = $this->_makeBatcher(6, reverse: true);

        self::assertSame(['line-5', 'line-4', 'line-3'], $this->_trimmed($batcher->getSlice(0, 3)));

        $batcher->reversePosition = $batcher->getReversePositionAfter(1);
        self::assertSame(['line-4', 'line-3', 'line-2'], $this->_trimmed($batcher->getSlice(1, 3)));

        $batcher->reversePosition = $batcher->getReversePositionAfter(0);
        self::assertSame(['line-4', 'line-3'], $this->_trimmed($batcher->getSlice(1, 2)));
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
        $this->_readAll($batcher, 2, usePositions: true);
    }

    public function testReverseThrowsWhenFileHasFewerLinesThanTotal(): void
    {
        $batcher = $this->_makeBatcher(3, reverse: true);
        $batcher->total = 6;

        $this->expectExceptionMessage('fewer lines than the expected total of 6');
        $this->_readAll($batcher, 2, usePositions: true);
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
        $batcher = $this->_makeBatcherFromContents(implode("\n", $this->_lines($total)) . ($trailingNewline ? "\n" : ''), $total);
        $batcher->reverse = $reverse;

        return $batcher;
    }

    private function _makeBatcherFromContents(string $contents, int $total): BulkDataBatcher
    {
        $this->_filePath = tempnam(sys_get_temp_dir(), 'shopify-batcher-');
        file_put_contents($this->_filePath, $contents);

        $batcher = new BulkDataBatcher();
        $batcher->filePath = $this->_filePath;
        $batcher->total = $total;
        $batcher->reverse = true;

        return $batcher;
    }

    /**
     * Reads the file the same way `BaseBatchedJob` does, advancing the offset by each item returned.
     * With `$usePositions`, each slice reads back from the position stored after the previous one, as the job does.
     *
     * @return string[]
     */
    private function _readAll(BulkDataBatcher $batcher, int $batchSize, bool $usePositions = false): array
    {
        $lines = [];
        $offset = 0;

        while ($offset < $batcher->count()) {
            $slice = $batcher->getSlice($offset, $batchSize);
            foreach ($slice as $line) {
                $offset++;
                if (trim($line) !== '') {
                    $lines[] = trim($line);
                }
            }

            if ($usePositions) {
                $batcher->reversePosition = $batcher->getReversePositionAfter(count($slice));
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
