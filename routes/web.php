<?php

use CraftCms\Shopify\Http\Controllers\WebhookController;
use Illuminate\Support\Facades\Route;

// Anonymous on purpose: the site URL Shopify 8 and earlier registered webhooks at.
Route::post('shopify/webhook/handle', WebhookController::class);
