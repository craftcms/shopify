<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\shopify\controllers;

use Craft;
use craft\helpers\Html;
use craft\shopify\Plugin;
use craft\web\Controller;
use craft\web\Response;
use Shopify\Auth\OAuth;
use Shopify\Auth\OAuthCookie;
use Shopify\Context;
use Shopify\Exception\InvalidOAuthException;
use Shopify\Utils;
use yii\web\Cookie;
use yii\web\Response as YiiResponse;

/**
 * The AuthController to manage the Shopify App authorization.
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 7.0.0
 */
class AuthController extends Controller
{
    /**
     * @inheritdoc
     */
    public function beforeAction($action): bool
    {
        if (!parent::beforeAction($action)) {
            return false;
        }

        // All actions in this controller should be restricted to users with explicit plugin permissions:
        $this->requirePermission('accessPlugin-' . $this->module->id);

        return true;
    }

    /**
     * @return YiiResponse
     * @throws \yii\base\InvalidConfigException
     */
    public function actionIndex(): YiiResponse
    {
        Plugin::getInstance()->getApi()->initializeContext();
        $settings = Plugin::getInstance()->getSettings();

        $screen = $this->asCpScreen()
            ->title(Craft::t('shopify', 'Authorization'));

        $validHmac = Utils::validateHmac(Craft::$app->getRequest()->getQueryParams(), $settings->getClientSecret());
        if (!$validHmac) {
            $html = $this->_errorHtml(Craft::t('shopify', 'Error authorizing app'), Craft::t('shopify', 'Invalid or missing HMAC. Please try re-installing the app.'));
            return $screen->contentHtml($html);
        }

        // If a code is present, it means the user has been redirected back from Shopify after authorizing the app.
        $code = Craft::$app->getRequest()->getQueryParam('code');
        if ($code) {
            $cookies = Craft::$app->getRequest()->getCookies()->toArray();
            foreach ($cookies as $name => $cookie) {
                if (!in_array($name, [OAuth::STATE_COOKIE_NAME, OAuth::STATE_SIG_COOKIE_NAME]) || !$cookie instanceof Cookie) {
                    continue;
                }

                $cookies[$name] = $cookie->value;
            }

            try {
                $accessToken = $this->_fetchAccessToken($cookies, Craft::$app->getRequest()->getQueryParams(), fn(OAuthCookie $oauthCookie) => $this->_setCookies($oauthCookie, $screen));

                if (!$accessToken) {
                    throw new InvalidOAuthException('Failed to retrieve access token.');
                }

                $html =
                    Html::beginTag('div', ['class' => 'flex flex-justify-center']) .
                        Html::beginTag('div', ['class' => 'pane centeralign']) .

                            Html::tag('p',
                                Html::tag('span', '', ['class' => 'checkmark-icon']) . ' ' .
                                Craft::t('shopify', 'Your Shopify app has been successfully authorized.')
                            ) .

                        Html::endTag('div') .
                    Html::endTag('div')
                ;

                return $screen->contentHtml($html);
            } catch (\Exception $e) {
                Craft::error($e->getMessage(), __METHOD__);

                $html = $this->_errorHtml(Craft::t('shopify', 'Error authorizing app'), $e->getMessage());

                return $screen->contentHtml($html);
            }
        }

        // If no code is present, it means the user is initiating the authorization process.
        $path = Plugin::getInstance()->getSettings()->getAuthPath();
        $shop = Craft::$app->getRequest()->getQueryParam('shop');
        $authorizeUrl = OAuth::begin($settings->getHostName(), $path, false, fn(OAuthCookie $oauthCookie) => $this->_setCookies($oauthCookie, $screen));

        $html = Html::beginTag('div', ['class' => 'flex flex-justify-center']) .
                Html::beginTag('div', ['class' => 'pane centeralign', 'style' => 'max-width: 400px']) .

                    Html::tag('h2', Craft::t('shopify', 'Authorize App')) .
                    Html::tag('p', Craft::t('shopify', 'The Shopify store {shop} needs to be authorized to connect with this plugin.', ['shop' => Html::tag('strong', $shop)])) .
                    Html::a(Craft::t('shopify', 'Authorize'), $authorizeUrl, ['class' => 'btn submit']) .

                Html::endTag('div') .
            Html::endTag('div');

        return $screen->contentHtml($html);
    }


    /**
     * @param array $cookies
     * @param array $query
     * @param callable|null $setCookieFunction
     * @return string|null
     * @throws InvalidOAuthException
     * @throws \Shopify\Exception\PrivateAppException
     * @throws \Shopify\Exception\UninitializedContextException
     * @throws \yii\base\InvalidConfigException
     */
    private function _fetchAccessToken(array $cookies, array $query, ?callable $setCookieFunction = null): ?string
    {
        Context::throwIfUninitialized();
        Context::throwIfPrivateApp('OAuth is not allowed for private apps');

        // `getCookie()`
        $signature = $cookies[OAuth::STATE_SIG_COOKIE_NAME] ?? null;
        $cookieId = $cookies[OAuth::STATE_COOKIE_NAME] ?? null;

        $cookieState = null;
        if ($signature && $cookieId) {
            $expectedSignature = hash_hmac('sha256', (string) $cookieId, Context::$API_SECRET_KEY);

            if ($signature === $expectedSignature) {
                $cookieState = $cookieId;
            }
        }

        if (!self::_isCallbackQueryValid($query, $cookieState)) {
            throw new InvalidOAuthException('Invalid OAuth callback.');
        }

        $sanitizedShop = Utils::sanitizeShopDomain($query['shop'] ?? '');
        return Plugin::getInstance()->getApi()->getAccessToken($query['code'], $sanitizedShop, true);
    }

    /**
     * @param array $query
     * @param string|null $stateCookie
     * @return bool
     */
    private static function _isCallbackQueryValid(array $query, string | null $stateCookie): bool
    {
        $sanitizedShop = Utils::sanitizeShopDomain($query['shop'] ?? '');
        $state = $query['state'] ?? '';
        $code = $query['code'] ?? '';

        return (
            ($code) &&
            ($sanitizedShop) &&
            ($state && $stateCookie && strcmp($stateCookie, (string) $state) === 0) &&
            Utils::validateHmac($query, Context::$API_SECRET_KEY)
        );
    }

    /**
     * @param OAuthCookie $oauthCookie
     * @param Response $screen
     * @return bool
     * @throws \yii\base\InvalidConfigException
     */
    private function _setCookies(OAuthCookie $oauthCookie, Response $screen): bool
    {
        $cookieConfig = Craft::cookieConfig([
            'name' => $oauthCookie->getName(),
            'value' => $oauthCookie->getValue(),
            'expire' => $oauthCookie->getExpire(),
        ]);

        $cookie = Craft::createObject(array_merge($cookieConfig, ['class' => Cookie::class]));

        $screen->getCookies()->add($cookie);

        return true;
    }

    /**
     * @param string $heading
     * @param string $message
     * @return string
     */
    private function _errorHtml(string $heading, string $message): string
    {
        return Html::beginTag('div', ['class' => 'flex flex-justify-center']) .
                Html::beginTag('div', [
                    'class' => ['error-summary fullwidth'],
                    'style' => 'max-width: 400px',
                ]) .
                    Html::beginTag('div') .
                        Html::tag('span', '', [
                            'class' => 'notification-icon',
                            'data-icon' => 'alert',
                            'aria-label' => Craft::t('app', 'Error'),
                            'role' => 'img',
                        ]) .
                        Html::tag('h2', $heading) .
                    Html::endTag('div') .
                    Html::beginTag('ul', [
                        'class' => ['errors'],
                    ]) .
                        Html::tag('li', $message) .
                    Html::endTag('ul') .
                Html::endTag('div') .
            Html::endTag('div');
    }
}
