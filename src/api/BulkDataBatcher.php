<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\shopify\api;

use craft\base\Batchable;

/**
 * BulkDataBatcher class.
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 6.0.0
 */
class BulkDataBatcher implements Batchable
{
    private const REVERSE_CHUNK_SIZE = 65536;

    /**
     * @var string|null
     */
    public ?string $filePath = null;

    /**
     * @var int
     */
    public int $total = 0;

    // TODO: make reverse reading the default in the next breaking version (9.0)
    /**
     * Whether lines should be read from the end of the file to the start.
     *
     * Shopify writes child objects (with a `__parentId`) after their parent, so reading in reverse ensures
     * every child has been read before its parent. When enabled, [[total]] must match the number of non-blank lines in the file.
     *
     * @var bool
     * @since 7.3.0
     */
    public bool $reverse = false;

    /**
     * The byte position in the file that the next reverse slice should end at.
     *
     * Batched jobs can store this between batches, so each slice is read without rescanning the rest of the file.
     * When `null`, the position is found by reading back from the end of the file by the slice’s offset.
     *
     * @var int|null
     * @since 7.3.0
     */
    public ?int $reversePosition = null;

    /**
     * @var int The byte position the last reverse slice ended at
     */
    private int $_reverseSliceEnd = 0;

    /**
     * @var int[] The byte positions of each line in the last reverse slice
     */
    private array $_reverseLinePositions = [];

    /**
     * @inerhitdoc
     */
    public function getSlice(int $offset, int $limit): iterable
    {
        if (!file_exists($this->filePath)) {
            throw new \Exception('File not found: ' . $this->filePath);
        }

        if (filesize($this->filePath) === 0) {
            throw new \Exception('File is empty: ' . $this->filePath);
        }

        $lines = $this->reverse ? $this->_getReverseSlice($offset, $limit) : $this->_getSlice($offset, $limit);

        if (empty($lines)) {
            throw new \Exception('No more lines to read from the file.');
        }

        return collect($lines);
    }

    /**
     * @inerhitdoc
     */
    public function count(): int
    {
        return $this->total;
    }

    /**
     * Returns the byte position the next reverse slice should end at, once the first `$count` lines of the last
     * reverse slice have been processed.
     *
     * @param int $count
     * @return int
     * @since 7.3.0
     */
    public function getReversePositionAfter(int $count): int
    {
        if ($count <= 0 || empty($this->_reverseLinePositions)) {
            return $this->_reverseSliceEnd;
        }

        return $this->_reverseLinePositions[min($count, count($this->_reverseLinePositions)) - 1];
    }

    /**
     * @param int $offset
     * @param int $limit
     * @return string[]
     */
    private function _getSlice(int $offset, int $limit): array
    {
        $fileObject = new \SplFileObject($this->filePath);
        $fileObject->seek($offset);

        $lines = [];
        $i = 0;
        while ($i < $limit && !$fileObject->eof()) {
            $lines[] = $fileObject->current();
            $fileObject->next();
            $i++;
        }

        return $lines;
    }

    /**
     * Returns the non-blank lines of a slice counted from the end of the file, last line first.
     *
     * @param int $offset
     * @param int $limit
     * @return string[]
     * @throws \Exception if the file doesn’t have exactly [[total]] non-blank lines
     */
    private function _getReverseSlice(int $offset, int $limit): array
    {
        $length = min($limit, $this->total - $offset);

        if ($length <= 0) {
            return [];
        }

        $handle = fopen($this->filePath, 'rb');

        try {
            $end = $this->reversePosition ?? $this->_findReversePosition($handle, $offset);
            [$lines, $positions] = $this->_readLinesBackwards($handle, $end, $length);

            if (count($lines) < $length) {
                throw new \Exception("The file has fewer lines than the expected total of $this->total: $this->filePath");
            }

            if ($offset + $length === $this->total && !empty($this->_readLinesBackwards($handle, end($positions), 1)[0])) {
                throw new \Exception("The file has more lines than the expected total of $this->total: $this->filePath");
            }
        } finally {
            fclose($handle);
        }

        $this->_reverseSliceEnd = $end;
        $this->_reverseLinePositions = $positions;

        return $lines;
    }

    /**
     * Returns the byte position that a reverse slice at the given offset ends at.
     *
     * @param resource $handle
     * @param int $offset
     * @return int
     * @throws \Exception if the file has fewer than `$offset` non-blank lines
     */
    private function _findReversePosition($handle, int $offset): int
    {
        $fileSize = (int)filesize($this->filePath);

        if ($offset === 0) {
            return $fileSize;
        }

        $positions = $this->_readLinesBackwards($handle, $fileSize, $offset)[1];

        if (count($positions) < $offset) {
            throw new \Exception("The file has fewer lines than the expected total of $this->total: $this->filePath");
        }

        return end($positions);
    }

    /**
     * Reads up to `$count` non-blank lines backwards from a byte position, in chunks.
     *
     * @param resource $handle
     * @param int $end The byte position to read back from
     * @param int $count
     * @return array{0: string[], 1: int[]} The lines, last line first, and the byte position each one starts at
     */
    private function _readLinesBackwards($handle, int $end, int $count): array
    {
        $lines = [];
        $positions = [];
        $bufferStart = $end;
        $buffer = '';

        while (count($lines) < $count) {
            $newline = strrpos($buffer, "\n");

            if ($newline === false) {
                if ($bufferStart === 0) {
                    if (trim($buffer) !== '') {
                        $lines[] = $buffer;
                        $positions[] = 0;
                    }

                    break;
                }

                $chunkSize = min(self::REVERSE_CHUNK_SIZE, $bufferStart);
                $bufferStart -= $chunkSize;
                fseek($handle, $bufferStart);
                $buffer = fread($handle, $chunkSize) . $buffer;

                continue;
            }

            $line = substr($buffer, $newline + 1);
            $buffer = substr($buffer, 0, $newline);

            if (trim($line) !== '') {
                $lines[] = $line;
                $positions[] = $bufferStart + $newline + 1;
            }
        }

        return [$lines, $positions];
    }
}
