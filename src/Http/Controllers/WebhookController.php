<?php

declare(strict_types=1);

namespace CraftCms\Shopify\Http\Controllers;

use craft\shopify\services\Api;
use craft\shopify\webhooks\WebhookRegistry;
use CraftCms\Shopify\Settings;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Receives webhooks from Shopify. Requests are anonymous, and are authenticated by their HMAC signature.
 */
readonly class WebhookController
{
    public function __invoke(Request $request, Api $api, Settings $settings): Response
    {
        abort_unless($api->connect(), 405, 'No Shopify API session found, check credentials in settings.');

        try {
            WebhookRegistry::process(
                $request->headers->all(),
                $request->getContent(),
                $settings->getClientSecret(),
            );
        } catch (Throwable $e) {
            Log::error($e->getMessage());
            abort(500, 'Could not process Shopify webhook. Check the logs for more information.');
        }

        return response('OK');
    }
}
