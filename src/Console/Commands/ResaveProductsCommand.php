<?php

declare(strict_types=1);

namespace CraftCms\Shopify\Console\Commands;

use CraftCms\Cms\Element\Commands\Resave\ResaveCommand;
use CraftCms\Cms\Support\Facades\Fields;
use CraftCms\Shopify\Product\Elements\Product;
use Override;

class ResaveProductsCommand extends ResaveCommand
{
    #[Override]
    protected $signature = 'craft:resave:shopify-products' . self::DEFAULT_OPTIONS;

    #[Override]
    protected $description = 'Re-saves Shopify products.';

    #[Override]
    protected $aliases = ['resave/shopify-products'];

    public function handle(): int
    {
        if (!$this->validateResaveOptions()) {
            return self::FAILURE;
        }

        if (!empty($this->resolvedWithFields) && !$this->hasTheFields(Fields::getLayoutByType(Product::class))) {
            $this->components->warn('The Shopify product field layout does not satisfy `--with-fields`.');

            return self::FAILURE;
        }

        return $this->resaveElements(Product::class);
    }
}
