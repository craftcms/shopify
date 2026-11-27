<?php

declare(strict_types=1);

namespace CraftCms\Shopify\Http\Controllers;

use CraftCms\Shopify\Api\Api;
use CraftCms\Shopify\Settings;
use CraftCms\Shopify\Webhook\WebhookRegistry;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Receives webhooks from Shopify. Requests are anonymous, and are authenticated by their HMAC signature.
 */
readonly class WebhookController
{
    public function __invoke(Request $request, Api $api, Settings $settings, WebhookRegistry $webhookRegistry): Response
    {
        abort_unless($api->connect(), 405, 'No Shopify API session found, check credentials in settings.');

        try {
            $webhookRegistry->process(
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
