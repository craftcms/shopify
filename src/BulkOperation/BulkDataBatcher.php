<?php

declare(strict_types=1);

namespace CraftCms\Shopify\BulkOperation;

use CraftCms\Cms\Queue\JsonlBatcher;

/**
 * Reads the JSONL results of a Shopify bulk operation in slices, from the last line to the first.
 *
 * Shopify writes child objects (with a `__parentId`) after their parent, so reading in reverse means every child
 * has been saved before its parent.
 */
class BulkDataBatcher extends JsonlBatcher
{
    /**
     * @param string $path The path to the downloaded data file.
     * @param int $total The number of objects in the file, which Shopify reports as the bulk operation’s `objectCount`.
     * @param int|null $position The byte position the next slice should read back from.
     */
    public function __construct(
        string $path,
        public readonly int $total,
        ?int $position = null,
    ) {
        parent::__construct($path, reverse: true, position: $position);
    }

    /**
     * Returns the object count Shopify reported, so the file isn’t read to count it, or needed before it’s downloaded.
     */
    public function count(): int
    {
        return $this->total;
    }
}
