<?php

declare(strict_types=1);

namespace CraftCms\Shopify\GarbageCollection\Actions;

use CraftCms\Cms\Config\GeneralConfig;
use CraftCms\Cms\GarbageCollection\Actions\GarbageCollectionAction;
use CraftCms\Cms\GarbageCollection\GarbageCollection;
use CraftCms\Shopify\BulkOperation\BulkOperations;

/**
 * Fails stuck bulk operations and deletes finished ones.
 */
class PurgeBulkOperations extends GarbageCollectionAction
{
    public function __construct(
        GarbageCollection $garbageCollection,
        GeneralConfig $generalConfig,
        private readonly BulkOperations $bulkOperations,
    ) {
        parent::__construct($garbageCollection, $generalConfig);
    }

    public function __invoke(): void
    {
        $this->components->task('purging Shopify bulk operations', function(): void {
            $this->bulkOperations->purgeBulkOperations();
        });
    }
}
