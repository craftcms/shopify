<?php

declare(strict_types=1);

namespace CraftCms\Shopify\Console\Commands;

use craft\shopify\elements\Product;
use CraftCms\Cms\Console\CraftCommand;
use CraftCms\Cms\Element\Elements;
use CraftCms\Shopify\Database\Table;
use Illuminate\Console\Command;
use Illuminate\Console\ConfirmableTrait;
use Illuminate\Support\Facades\DB;
use Override;
use Throwable;

class DataResetCommand extends Command
{
    use CraftCommand;
    use ConfirmableTrait;

    #[Override]
    protected $signature = 'shopify:data:reset {--force : Reset the data without confirmation}';

    #[Override]
    protected $description = 'Deletes all Shopify products, product data and sync history from Craft. Data in Shopify is not affected.';

    #[Override]
    protected $aliases = ['shopify:data', 'shopify:data:reset', 'shopify/data', 'shopify/data/reset'];

    public function handle(Elements $elements): int
    {
        $confirmed = $this->confirmToProceed(
            'Resetting Shopify data permanently deletes all products, variants and sync history from Craft. Data in Shopify is not affected.',
            fn() => true,
        );

        if (!$confirmed) {
            return self::SUCCESS;
        }

        try {
            DB::transaction(function() use ($elements) {
                $this->components->task('Removing products', function() use ($elements) {
                    foreach (Product::find()->status(null)->unique()->cursor() as $product) {
                        $elements->deleteElement($product, true);
                    }
                });

                $this->components->task('Removing Shopify data', fn() => DB::table(Table::DATA)->delete());
                $this->components->task('Removing sync history', fn() => DB::table(Table::BULK_OPERATIONS)->delete());
            });
        } catch (Throwable $e) {
            $this->components->error($e->getMessage());

            return self::FAILURE;
        }

        $this->components->info('Finished resetting Shopify data.');

        return self::SUCCESS;
    }
}
