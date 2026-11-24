<?php

declare(strict_types=1);

namespace CraftCms\Shopify\Http\Controllers;

use craft\shopify\services\BulkOperations;
use CraftCms\Cms\Http\RespondsWithFlash;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

use function CraftCms\Cms\t;

readonly class SyncController
{
    use RespondsWithFlash;

    public function destroy(Request $request, BulkOperations $bulkOperations): Response
    {
        $id = $request->integer('id');
        abort_unless($id > 0, 400, 'Missing sync id');

        if ($bulkOperations->deleteBulkOperationById($id)) {
            return $this->asSuccess(t('Sync deleted', category: 'shopify'));
        }

        return $this->asFailure(t('Failed to delete sync', category: 'shopify'));
    }
}
