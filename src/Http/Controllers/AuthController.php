<?php

declare(strict_types=1);

namespace CraftCms\Shopify\Http\Controllers;

use craft\shopify\auth\OAuthFlow;
use craft\shopify\exceptions\InvalidOAuthException;
use craft\shopify\helpers\ShopifyHelper;
use craft\shopify\services\Api;
use CraftCms\Cms\Cp\Components\Button;
use CraftCms\Cms\Cp\Components\EmptyState;
use CraftCms\Cms\Cp\Enums\ButtonVariant;
use CraftCms\Cms\Http\Responses\CpScreenResponse;
use CraftCms\Cms\Support\Html;
use CraftCms\Cms\Ui\Contracts\Node;
use CraftCms\Cms\Ui\Nodes\Callout;
use CraftCms\Cms\Ui\Nodes\TemplateContent;
use CraftCms\Cms\Ui\Ui;
use CraftCms\Shopify\Settings;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\HtmlString;
use Throwable;

use function CraftCms\Cms\t;

/**
 * Authorizes the Shopify app. Shopify sends the user here to install the app, and again with a code once they’ve
 * approved it, so the URL must stay `{cpTrigger}/shopify/auth`.
 */
readonly class AuthController
{
    public function __construct(
        private Settings $settings,
        private Api $api,
    ) {
    }

    public function __invoke(Request $request): CpScreenResponse
    {
        if (!ShopifyHelper::validateHmac($this->rawQuery($request), $this->settings->getClientSecret())) {
            return $this->screen($this->error(t('Invalid or missing HMAC. Please try re-installing the app.', category: 'shopify')));
        }

        if ($request->query('code')) {
            try {
                if (!$this->fetchAccessToken($request)) {
                    throw new InvalidOAuthException('Failed to retrieve access token.');
                }
            } catch (Throwable $e) {
                Log::error($e->getMessage());

                return $this->screen($this->error($e->getMessage()));
            }

            return $this->screen(TemplateContent::make('authorized', EmptyState::make()
                ->icon('circle-check')
                ->label(t('Your Shopify app has been successfully authorized.', category: 'shopify'))
                ->toHtml())->trusted());
        }

        $authorizeUrl = OAuthFlow::begin(
            $this->settings->getHostName(),
            false,
            $this->settings->getClientId(),
            $this->settings->getScopes(),
            $this->settings->getClientSecret(),
            function(string $name, string $value, int $expire) {
                Cookie::queue($name, $value, max(1, (int)ceil(($expire - time()) / 60)));

                return true;
            },
        );

        return $this->screen(TemplateContent::make('authorize', EmptyState::make()
            ->label(t('Authorize App', category: 'shopify'))
            ->message(new HtmlString(t('The Shopify store {shop} needs to be authorized to connect with this plugin.', [
                'shop' => Html::tag('strong', Html::encode((string)$request->query('shop'))),
            ], category: 'shopify')))
            ->actions(Button::make()
                ->label(t('Authorize', category: 'shopify'))
                ->variant(ButtonVariant::Primary)
                ->href($authorizeUrl))
            ->toHtml())->trusted());
    }

    private function screen(Node $node): CpScreenResponse
    {
        return new CpScreenResponse()
            ->title(t('Authorization', category: 'shopify'))
            ->ui(Ui::make([$node]));
    }

    private function error(string $message): Callout
    {
        return Callout::make('error', '**' . t('Error authorizing app', category: 'shopify') . "**\n\n" . $message)
            ->variant('danger');
    }

    /**
     * @throws InvalidOAuthException
     */
    private function fetchAccessToken(Request $request): ?string
    {
        $state = $request->cookie(OAuthFlow::STATE_COOKIE_NAME);
        $signature = $request->cookie(OAuthFlow::STATE_SIG_COOKIE_NAME);

        $validState = is_string($state)
            && is_string($signature)
            && hash_equals(hash_hmac('sha256', $state, $this->settings->getClientSecret()), $signature)
            && hash_equals($state, (string)$request->query('state'));

        $shop = ShopifyHelper::sanitizeShopDomain((string)$request->query('shop'));

        if (!$validState || !$shop || !ShopifyHelper::validateHmac($this->rawQuery($request), $this->settings->getClientSecret())) {
            throw new InvalidOAuthException('Invalid OAuth callback.');
        }

        return $this->api->getAccessToken((string)$request->query('code'), $shop, true);
    }

    /**
     * Returns the query params as Shopify signed them, before middleware trims or nulls any values.
     *
     * @return array<string, mixed>
     */
    private function rawQuery(Request $request): array
    {
        parse_str((string)$request->server('QUERY_STRING'), $query);

        return $query;
    }
}
