<?php

use CraftCms\Cms\Http\Middleware\RequireAdmin;
use CraftCms\Cms\Http\Middleware\RequireAdminChanges;
use CraftCms\Shopify\Http\Controllers\ProductsController;
use CraftCms\Shopify\Http\Controllers\SettingsController;
use CraftCms\Shopify\Http\Controllers\SyncController;
use CraftCms\Shopify\Http\Controllers\WebhookController;
use CraftCms\Shopify\Http\Controllers\WebhooksController;
use Illuminate\Support\Facades\Route;

// Anonymous on purpose: Shopify posts webhooks here, signed with an HMAC instead of a session.
// Stores subscribe at both `actions/shopify/webhook/handle` and `{cpTrigger}/actions/shopify/webhook/handle`,
// so neither URL can require a login.
Route::withoutMiddleware(['auth', 'can:accessCp'])->group(function() {
    Route::post('webhook', WebhookController::class);
    Route::post('webhook/handle', WebhookController::class);
});

Route::middleware(['auth', 'can:accessPlugin-shopify'])->group(function() {
    Route::post('webhooks/create', [WebhooksController::class, 'store']);
    Route::match(['post', 'delete'], 'webhooks/delete', [WebhooksController::class, 'destroy']);

    Route::middleware('can:utility:shopify-sync')->group(function() {
        Route::post('products/sync', [ProductsController::class, 'sync']);
        Route::match(['post', 'delete'], 'sync/delete', [SyncController::class, 'destroy']);
    });
});

Route::middleware('auth')->group(function() {
    Route::middleware(RequireAdminChanges::class)->post('settings/save-settings', [SettingsController::class, 'update']);
    Route::middleware(RequireAdmin::class)->post('settings/render-ui', [SettingsController::class, 'renderUi']);
});
