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

        // Seek forward in the file by the number of lines in the offset
        $fileObject = new \SplFileObject($this->filePath);
        $fileObject->seek($offset === 0 ? 0 : $offset - 1);

        $lines = [];
        $i = 0;
        while ($i < $limit && !$fileObject->eof()) {
            $lines[] = $fileObject->current();
            $fileObject->next();
            $i++;
        }

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
}
