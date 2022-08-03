<?php
/**
 * Shopify plugin for Craft CMS 4.x
 *
 * Shopify for Craft CMS
 *
 * @link      https://craftcms.com
 * @copyright Copyright (c) 2022 Pixel & Tonic, Inc
 */

namespace craft\shopify;

use Craft;
use craft\base\Model;
use craft\base\Plugin as BasePlugin;
use craft\events\RegisterComponentTypesEvent;
use craft\events\RegisterUrlRulesEvent;
use craft\helpers\UrlHelper;
use craft\services\Elements;
use craft\shopify\elements\Product;
use craft\shopify\handlers\Product as ProductHandler;
use craft\shopify\models\Settings;
use craft\shopify\services\Api;
use craft\shopify\services\ProductData;
use craft\web\UrlManager;
use Shopify\Webhooks\Registry;
use Shopify\Webhooks\Topics;
use yii\base\Event;
use yii\base\InvalidConfigException;

/**
 * Class Shopify
 *
 * @author    Pixel & Tonic, Inc
 * @package   Shopify
 * @since     1.0
 *
 * @property-read null|array $cpNavItem
 * @property Settings $settings
 * @method Settings getSettings()
 */
class Plugin extends BasePlugin
{
    /**
     * @var string
     */
    public string $schemaVersion = '0.0.1';
    /**
     * @inheritdoc
     */
    public bool $hasCpSettings = true;

    /**
     * @inheritdoc
     */
    public bool $hasCpSection = true;

    /**
     * @inheritdoc
     */
    public static function config(): array
    {
        return [
            'components' => [
                'api' => ['class' => Api::class],
                'products' => ['class' => ProductData::class],
            ]
        ];
    }

    /**
     * @inheritdoc
     */
    protected function createSettingsModel(): ?Model
    {
        return new Settings();
    }

    /**
     * @inheritdoc
     */
    public function getSettingsResponse(): mixed
    {
        return Craft::$app->getResponse()->redirect(UrlHelper::cpUrl('shopify/settings'));
    }

    /**
     * @inheritdoc
     */
    public function init()
    {
        $request = Craft::$app->getRequest();

        $this->_registerElementTypes();
        if ($request->getIsConsoleRequest()) {
            // _registerConsoleStuff
        } elseif ($request->getIsCpRequest()) {
            $this->_registerCpRoutes();
        } else {
            $this->_registerSiteRoutes();
        }

        Event::on(UrlManager::class, UrlManager::EVENT_REGISTER_SITE_URL_RULES, function(RegisterUrlRulesEvent $event) {
            $event->rules['shopify/webhook/handle'] = 'shopify/webhook/handle';
        });

        Registry::addHandler(Topics::PRODUCTS_CREATE, new ProductHandler());
        Registry::addHandler(Topics::PRODUCTS_DELETE, new ProductHandler());
        Registry::addHandler(Topics::PRODUCTS_UPDATE, new ProductHandler());
    }

    /**
     * Returns the API service
     *
     * @return Api The API service
     * @throws InvalidConfigException
     */
    public function getApi(): Api
    {
        return $this->get('api');
    }

    /**
     * Returns the ProductData service
     *
     * @return ProductData The ProductData service
     * @throws InvalidConfigException
     */
    public function getProducts(): ProductData
    {
        return $this->get('products');
    }

    /**
     * Register the element types supplied by Shopify
     */
    private function _registerElementTypes(): void
    {
        Event::on(Elements::class, Elements::EVENT_REGISTER_ELEMENT_TYPES, static function (RegisterComponentTypesEvent $e) {
            $e->types[] = Product::class;
        });
    }


    /**
     * @since 1.0
     */
    private function _registerCpRoutes(): void
    {
        Event::on(UrlManager::class, UrlManager::EVENT_REGISTER_CP_URL_RULES, function (RegisterUrlRulesEvent $event) {
            $event->rules['shopify'] = ['template' => 'shopify/_index'];
            $event->rules['shopify/products'] = 'shopify/products/product-index';
            $event->rules['shopify/sync-products'] = 'shopify/products/sync';
            $event->rules['shopify/products/<elementId:\d+>'] = 'elements/edit';
            $event->rules['shopify/settings'] = 'shopify/settings';
        });
    }

    /**
     * Registers the
     */
    private function _registerSiteRoutes(): void
    {
        Event::on(UrlManager::class, UrlManager::EVENT_REGISTER_SITE_URL_RULES, function (RegisterUrlRulesEvent $event) {
            $event->rules['shopify/webhook'] = 'shopify/products/product-index';
        });
    }

    /**
     * @inheritdoc
     */
    public function getCpNavItem(): ?array
    {
        $ret = parent::getCpNavItem();

        $ret['label'] = Craft::t('shopify', 'Shopify');

        $ret['subnav']['orders'] = [
            'label' => Craft::t('shopify', 'Products'),
            'url' => 'shopify/products',
        ];

        if (Craft::$app->getUser()->getIsAdmin() && Craft::$app->getConfig()->getGeneral()->allowAdminChanges) {
            $ret['subnav']['settings'] = [
                'label' => Craft::t('shopify', 'Settings'),
                'url' => 'shopify/settings',
            ];
        }


        return $ret;
    }
}
