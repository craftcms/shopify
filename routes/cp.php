<?php

use craft\shopify\services\Api;
use CraftCms\Cms\Http\Controllers\Elements\EditElementController;
use CraftCms\Cms\Http\Middleware\RequireAdmin;
use CraftCms\Shopify\Http\Controllers\AuthController;
use CraftCms\Shopify\Http\Controllers\ProductsController;
use CraftCms\Shopify\Http\Controllers\SettingsController;
use CraftCms\Shopify\Http\Controllers\WebhooksController;
use Illuminate\Support\Facades\Route;

use function CraftCms\Cms\cp_url;

Route::middleware('can:accessPlugin-shopify')->group(function() {
    Route::get('shopify', fn(Api $api) => redirect(cp_url($api->connect() ? 'shopify/products' : 'shopify/settings')));
    Route::get('shopify/products', [ProductsController::class, 'index']);
    Route::get('shopify/products/{id}', EditElementController::class)->whereNumber('id');
    Route::get('shopify/webhooks', [WebhooksController::class, 'index']);

    // Shopify redirects here to install the app and after authorizing it, so the URL must not change.
    Route::get('shopify/auth', AuthController::class);
});

// Admins can view the settings when admin changes are disallowed, read-only.
Route::middleware(RequireAdmin::class)->get('shopify/settings', [SettingsController::class, 'edit']);
