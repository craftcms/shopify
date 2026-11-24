<?php

declare(strict_types=1);

namespace CraftCms\Shopify\Console\Commands;

use Override;

class SyncAllCommand extends SyncProductsCommand
{
    #[Override]
    protected $signature = 'shopify:sync:all';

    #[Override]
    protected $description = 'Requests a sync of all Shopify data.';

    #[Override]
    protected $aliases = ['shopify:sync', 'shopify:sync:all', 'shopify/sync', 'shopify/sync/all'];
}
