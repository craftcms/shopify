<?php

declare(strict_types=1);

namespace CraftCms\Shopify\Console\Commands;

use craft\shopify\services\BulkOperations;
use CraftCms\Cms\Console\CraftCommand;
use Illuminate\Console\Command;
use Override;

class SyncProductsCommand extends Command
{
    use CraftCommand;

    #[Override]
    protected $signature = 'shopify:sync:products';

    #[Override]
    protected $description = 'Requests a sync of all Shopify products.';

    #[Override]
    protected $aliases = ['shopify:sync:products', 'shopify/sync/products'];

    public function handle(BulkOperations $bulkOperations): int
    {
        if ($bulkOperations->createProductsBulkOperation() === false) {
            $this->components->error('Failed to create a bulk operation.');

            return self::FAILURE;
        }

        $this->components->info('Shopify products sync requested.');

        return self::SUCCESS;
    }
}
