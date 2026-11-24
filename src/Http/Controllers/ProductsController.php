<?php

declare(strict_types=1);

namespace CraftCms\Shopify\Http\Controllers;

use craft\shopify\services\BulkOperations;
use CraftCms\Cms\Http\RespondsWithFlash;
use CraftCms\Shopify\Settings;
use Symfony\Component\HttpFoundation\Response;

use function CraftCms\Cms\pageTemplate;
use function CraftCms\Cms\t;

readonly class ProductsController
{
    use RespondsWithFlash;

    public function index(Settings $settings): string
    {
        $hostName = $settings->getHostName();

        return pageTemplate('shopify/products/_index', [
            'newProductUrl' => $hostName ? "https://$hostName/admin/products/new" : '',
        ]);
    }

    public function sync(BulkOperations $bulkOperations): Response
    {
        if ($bulkOperations->createProductsBulkOperation() === false) {
            return $this->asFailure(t('Failed to create products sync', category: 'shopify'));
        }

        return $this->asSuccess(t('Products sync created', category: 'shopify'));
    }
}
