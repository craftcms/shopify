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
use craft\console\Controller;
use craft\console\controllers\ResaveController;
use craft\events\DefineConsoleActionsEvent;
use craft\events\RegisterComponentTypesEvent;
use craft\events\RegisterUrlRulesEvent;
use craft\helpers\UrlHelper;
use craft\services\Elements;
use craft\services\Fields;
use craft\services\Utilities;
use craft\shopify\elements\Product;
use craft\shopify\fields\Products as ProductsField;
use craft\shopify\handlers\Product as ProductHandler;
use craft\shopify\models\Settings;
use craft\shopify\services\Api;
use craft\shopify\services\Products;
use craft\shopify\services\Store;
use craft\shopify\utilities\Sync;
use craft\shopify\web\twig\CraftVariableBehavior;
use craft\web\twig\variables\CraftVariable;
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
    public const PC_PATH_PRODUCT_FIELD_LAYOUTS = 'shopify.productFieldLayout';

    /**
     * @var string
     */
    public string $schemaVersion = '4.0.6'; // For some reason the 2.2+ version of the plugin was at 4.0 schema version

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
                'products' => ['class' => Products::class],
                'store' => ['class' => Store::class],
            ],
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
        $this->_registerUtilityTypes();
        $this->_registerFieldTypes();
        $this->_registerVariables();
        $this->_registerResaveCommands();

        if (!$request->getIsConsoleRequest()) {
            if ($request->getIsCpRequest()) {
                $this->_registerCpRoutes();
            } else {
                $this->_registerSiteRoutes();
            }
        }

        $projectConfigService = Craft::$app->getProjectConfig();
        $productsService = $this->getProducts();

        $projectConfigService->onAdd(self::PC_PATH_PRODUCT_FIELD_LAYOUTS, [$productsService, 'handleChangedFieldLayout'])
            ->onUpdate(self::PC_PATH_PRODUCT_FIELD_LAYOUTS, [$productsService, 'handleChangedFieldLayout'])
            ->onRemove(self::PC_PATH_PRODUCT_FIELD_LAYOUTS, [$productsService, 'handleDeletedFieldLayout']);

        // Globally register shopify webhooks registry event handlers
        Registry::addHandler(Topics::PRODUCTS_CREATE, new ProductHandler());
        Registry::addHandler(Topics::PRODUCTS_DELETE, new ProductHandler());
        Registry::addHandler(Topics::PRODUCTS_UPDATE, new ProductHandler());
        Registry::addHandler(Topics::INVENTORY_LEVELS_UPDATE, new ProductHandler());
    }

    /**
     * Returns the API service
     *
     * @return Api The API service
     * @throws InvalidConfigException
     * @since 3.0
     */
    public function getApi(): Api
    {
        return $this->get('api');
    }

    /**
     * Returns the ProductData service
     *
     * @return Products The Products service
     * @throws InvalidConfigException
     * @since 3.0
     */
    public function getProducts(): Products
    {
        return $this->get('products');
    }

    /**
     * Returns the API service
     *
     * @return Store The Store service
     * @throws InvalidConfigException
     * @since 3.0
     */
    public function getStore(): Store
    {
        return $this->get('store');
    }

    /**
     * Registers the utilities.
     *
     * @since 3.0
     */
    private function _registerUtilityTypes(): void
    {
        Event::on(
            Utilities::class,
            Utilities::EVENT_REGISTER_UTILITIES,
            function(RegisterComponentTypesEvent $event) {
                $event->types[] = Sync::class;
            }
        );
    }

    /**
     * Register the element types supplied by Shopify
     *
     * @since 3.0
     */
    private function _registerElementTypes(): void
    {
        Event::on(Elements::class, Elements::EVENT_REGISTER_ELEMENT_TYPES, static function(RegisterComponentTypesEvent $e) {
            $e->types[] = Product::class;
        });
    }

    /**
     * Register Shopify’s fields
     *
     * @since 3.0
     */
    private function _registerFieldTypes(): void
    {
        Event::on(Fields::class, Fields::EVENT_REGISTER_FIELD_TYPES, static function(RegisterComponentTypesEvent $event) {
            $event->types[] = ProductsField::class;
        });
    }

    /**
     * Register Shopify twig variables to the main craft variable
     *
     * @since 3.0
     */
    private function _registerVariables(): void
    {
        Event::on(CraftVariable::class, CraftVariable::EVENT_INIT, static function(Event $event) {
            $variable = $event->sender;
            $variable->attachBehavior('shopify', CraftVariableBehavior::class);
        });
    }

    public function _registerResaveCommands(): void
    {
        Event::on(ResaveController::class, Controller::EVENT_DEFINE_ACTIONS, static function(DefineConsoleActionsEvent $e) {
            $e->actions['shopify-products'] = [
                'action' => function(): int {
                    /** @var ResaveController $controller */
                    $controller = Craft::$app->controller;
                    return $controller->resaveElements(Product::class);
                },
                'options' => [],
                'helpSummary' => 'Re-saves Shopify products.',
            ];
        });
    }

    /**
     * Register the CP routes
     *
     * @since 3.0
     */
    private function _registerCpRoutes(): void
    {
        Event::on(UrlManager::class, UrlManager::EVENT_REGISTER_CP_URL_RULES, function(RegisterUrlRulesEvent $event) {
            $session = Plugin::getInstance()->getApi()->getSession();
            $event->rules['shopify'] = ['template' => 'shopify/_index', 'variables' => ['hasSession' => (bool)$session]];

            $event->rules['shopify/products'] = 'shopify/products/product-index';
            $event->rules['shopify/sync-products'] = 'shopify/products/sync';
            $event->rules['shopify/products/<elementId:\d+>'] = 'elements/edit';
            $event->rules['shopify/settings'] = 'shopify/settings';
            $event->rules['shopify/webhooks'] = 'shopify/webhooks/edit';
        });
    }

    /**
     * Registers the Site routes.
     *
     * @since 3.0
     */
    private function _registerSiteRoutes(): void
    {
        Event::on(UrlManager::class, UrlManager::EVENT_REGISTER_SITE_URL_RULES, function(RegisterUrlRulesEvent $event) {
            $event->rules['shopify/webhook/handle'] = 'shopify/webhook/handle';
        });
    }

    /**
     * @inheritdoc
     */
    public function getCpNavItem(): ?array
    {
        $ret = parent::getCpNavItem();
        $ret['label'] = Craft::t('shopify', 'Shopify');

        $session = Plugin::getInstance()->getApi()->getSession();

        if ($session) {
            $ret['subnav']['products'] = [
                'label' => Craft::t('shopify', 'Products'),
                'url' => 'shopify/products',
            ];
        }

        if (Craft::$app->getUser()->getIsAdmin() && Craft::$app->getConfig()->getGeneral()->allowAdminChanges) {
            $ret['subnav']['settings'] = [
                'label' => Craft::t('shopify', 'Settings'),
                'url' => 'shopify/settings',
            ];
        }

        if ($session) {
            if (Craft::$app->getUser()->getIsAdmin()) {
                $ret['subnav']['webhooks'] = [
                    'label' => Craft::t('shopify', 'Webhooks'),
                    'url' => 'shopify/webhooks',
                ];
            }
        }


        return $ret;
    }
}
