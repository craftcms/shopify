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
     * every child has been read before its parent. When enabled, [[total]] must match the number of lines in the file.
     *
     * @var bool
     * @since 7.3.0
     */
    public bool $reverse = false;

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
     * Returns the lines of a slice counted from the end of the file, last line first.
     *
     * @param int $offset
     * @param int $limit
     * @return string[]
     * @throws \Exception if the file doesn’t have exactly [[total]] lines
     */
    private function _getReverseSlice(int $offset, int $limit): array
    {
        $length = min($limit, $this->total - $offset);

        if ($length <= 0) {
            return [];
        }

        $start = $this->total - $offset - $length;
        $fileObject = new \SplFileObject($this->filePath);
        $fileObject->seek($start);

        $lines = [];
        for ($i = 0; $i < $length; $i++) {
            if ($fileObject->eof() || $fileObject->key() !== $start + $i) {
                throw new \Exception("The file has fewer lines than the expected total of $this->total: $this->filePath");
            }

            $lines[] = $fileObject->current();
            $fileObject->next();
        }

        if ($offset === 0 && !$fileObject->eof() && trim((string)$fileObject->current()) !== '') {
            throw new \Exception("The file has more lines than the expected total of $this->total: $this->filePath");
        }

        return array_reverse($lines);
    }
}
