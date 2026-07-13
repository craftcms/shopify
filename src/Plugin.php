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
use craft\console\Application as ConsoleApplication;
use craft\console\Controller;
use craft\console\controllers\ResaveController;
use craft\db\Query;
use craft\events\DefineConsoleActionsEvent;
use craft\events\DefineFieldLayoutFieldsEvent;
use craft\events\RegisterComponentTypesEvent;
use craft\events\RegisterGqlQueriesEvent;
use craft\events\RegisterGqlSchemaComponentsEvent;
use craft\events\RegisterGqlTypesEvent;
use craft\events\RegisterUrlRulesEvent;
use craft\feedme\events\RegisterFeedMeFieldsEvent;
use craft\fields\Link;
use craft\helpers\ArrayHelper;
use craft\helpers\Console;
use craft\helpers\UrlHelper;
use craft\models\FieldLayout;
use craft\services\Elements;
use craft\services\Fields;
use craft\services\Gc;
use craft\services\Gql;
use craft\services\Utilities;
use craft\shopify\db\Table;
use craft\shopify\elements\Product;
use craft\shopify\feedme\fields\Products as FeedMeProductsField;
use craft\shopify\fieldlayoutelements\MediaField;
use craft\shopify\fieldlayoutelements\MetafieldsField;
use craft\shopify\fieldlayoutelements\OptionsField;
use craft\shopify\fieldlayoutelements\VariantsField;
use craft\shopify\fields\Products as ProductsField;
use craft\shopify\gql\interfaces\elements\Product as GqlProductInterface;
use craft\shopify\gql\queries\Product as GqlProductQueries;
use craft\shopify\handlers\Webhook;
use craft\shopify\linktypes\Product as ProductLinkType;
use craft\shopify\models\Settings;
use craft\shopify\services\Api;
use craft\shopify\services\BulkOperations;
use craft\shopify\services\Products;
use craft\shopify\services\Store;
use craft\shopify\utilities\Sync;
use craft\shopify\web\twig\CraftVariableBehavior;
use craft\shopify\webhooks\WebhookRegistry;
use craft\web\twig\variables\CraftVariable;
use craft\web\UrlManager;
use GraphQL\Query as GqlQuery;
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
    public string $schemaVersion = '8.0.0';

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
    public string $minVersionRequired = '4.0.0';

    /**
     * @inheritdoc
     */
    public static function config(): array
    {
        return [
            'components' => [
                'api' => ['class' => Api::class],
                'bulkOperations' => ['class' => BulkOperations::class],
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
        $this->_registerFieldLayoutElements();
        $this->_registerLinkTypes();
        $this->_registerVariables();
        $this->_registerResaveCommands();
        $this->_registerGarbageCollection();
        $this->_registerFeedMeEvents();
        $this->_registerGqlInterfaces();
        $this->_registerGqlQueries();
        $this->_registerGqlComponents();

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
        foreach ($this->getApi()::WEBHOOK_TOPICS as $topic) {
            WebhookRegistry::addHandler($topic, new Webhook());
        }
    }

    /**
     * @return BulkOperations
     * @throws InvalidConfigException
     * @since 6.0.0
     */
    public function getBulkOperations(): BulkOperations
    {
        return $this->get('bulkOperations');
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
     * @return void
     */
    private function _registerFeedMeEvents(): void
    {
        $feedMePlugin = Craft::$app->getPlugins()->getPlugin('feed-me');
        if (!class_exists(\craft\feedme\services\Fields::class) || !$feedMePlugin) {
            return;
        }

        Event::on(\craft\feedme\services\Fields::class, \craft\feedme\services\Fields::EVENT_REGISTER_FEED_ME_FIELDS, function(RegisterFeedMeFieldsEvent $event) {
            $event->fields[] = FeedMeProductsField::class;
        });
    }

    /**
     * Registers the utilities.
     *
     * @since 3.0
     */
    private function _registerUtilityTypes(): void
    {
        /** @phpstan-ignore-next-line */
        $eventName = defined(Utilities::class . '::EVENT_REGISTER_UTILITIES') ? Utilities::EVENT_REGISTER_UTILITIES : Utilities::EVENT_REGISTER_UTILITY_TYPES;

        Event::on(
            Utilities::class,
            $eventName,
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
     * Register the Gql interfaces
     * @since 7.1.0
     */
    private function _registerGqlInterfaces(): void
    {
        Event::on(Gql::class, Gql::EVENT_REGISTER_GQL_TYPES, static function(RegisterGqlTypesEvent $event) {
            $event->types[] = GqlProductInterface::class;
        });
    }

    /**
     * Register the Gql queries
     * @since 7.1.0
     */
    private function _registerGqlQueries(): void
    {
        Event::on(Gql::class, Gql::EVENT_REGISTER_GQL_QUERIES, static function(RegisterGqlQueriesEvent $event) {
            $event->queries = array_merge(
                $event->queries,
                GqlProductQueries::getQueries(),
            );
        });
    }

    /**
     * Register the Gql permissions
     * @since 7.1.0
     */
    private function _registerGqlComponents(): void
    {
        Event::on(Gql::class, Gql::EVENT_REGISTER_GQL_SCHEMA_COMPONENTS, static function(RegisterGqlSchemaComponentsEvent $event) {
            $typeName = (new Product())->getGqlTypeName();
            $event->queries = array_merge($event->queries, [
                Craft::t('shopify', 'Shopify Products') => [
                    $typeName . ':read' => ['label' => Craft::t('shopify', 'View products')],
                ],
            ]);
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
     * @return void
     * @since 7.0.0
     */
    private function _registerFieldLayoutElements(): void
    {
        Event::on(FieldLayout::class, FieldLayout::EVENT_DEFINE_NATIVE_FIELDS, static function(DefineFieldLayoutFieldsEvent $e) {
            /** @var FieldLayout $fieldLayout */
            $fieldLayout = $e->sender;

            switch ($fieldLayout->type) {
                case Product::class:
                    $e->fields[] = VariantsField::class;
                    $e->fields[] = OptionsField::class;
                    $e->fields[] = MetafieldsField::class;
                    $e->fields[] = MediaField::class;
                    break;
            }
        });
    }

    /**
     * Register Link types
     *
     * @since 5.2.0
     */
    private function _registerLinkTypes(): void
    {
        if (!class_exists(Link::class)) {
            return;
        }

        Event::on(Link::class, Link::EVENT_REGISTER_LINK_TYPES, function(RegisterComponentTypesEvent $event) {
            $event->types[] = ProductLinkType::class;
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
            $event->rules['shopify'] = ['template' => 'shopify/_index', 'variables' => ['hasSession' => Plugin::getInstance()->getApi()->connect()]];

            $event->rules['shopify/products'] = 'shopify/products/product-index';
            $event->rules['shopify/sync-products'] = 'shopify/products/sync';
            $event->rules['shopify/products/<elementId:\d+>'] = 'elements/edit';
            $event->rules['shopify/settings'] = 'shopify/settings';
            $event->rules['shopify/webhooks'] = 'shopify/webhooks/edit';
            $event->rules['shopify/auth'] = 'shopify/auth/index';
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
     * Register the things that need to be garbage collected
     *
     * @since 6.0.0
     */
    private function _registerGarbageCollection(): void
    {
        Event::on(Gc::class, Gc::EVENT_RUN, function(Event $event) {
            // Deletes carts that meet the purge settings
            if (Craft::$app instanceof ConsoleApplication) {
                Console::stdout('    > purging syncs ... ');
            }

            Plugin::getInstance()->getBulkOperations()->purgeBulkOperations();

            if (Craft::$app instanceof ConsoleApplication) {
                Console::stdout("done\n", Console::FG_GREEN);
            }

            if (Craft::$app instanceof ConsoleApplication) {
                Console::stdout('    > deleting partial product elements ... ');
            }

            /** @var Gc $gc */
            $gc = $event->sender;
            $gc->deletePartialElements(Product::class, Table::PRODUCTS, 'id');

            if (Craft::$app instanceof ConsoleApplication) {
                Console::stdout("done\n", Console::FG_GREEN);
            }

            // See if there are any orphaned products that no longer exist in Shopify
            $shopifyProductElementsMissingData = (new Query())
                ->select([
                    'products.id',
                    'products.shopifyId',
                ])
                ->from(Table::PRODUCTS . ' products')
                ->leftJoin(Table::DATA . ' data', '[[data.shopifyGid]] = [[products.shopifyGid]]')
                ->where(['data.shopifyGid' => null])
                ->all();

            $shopifyIds = ArrayHelper::getColumn($shopifyProductElementsMissingData, 'shopifyId');

            if (!empty($shopifyIds)) {
                if (Craft::$app instanceof ConsoleApplication) {
                    Console::stdout('    > deleting product elements not in Shopify ... ');
                }

                $startCursor = null;
                $savedShopifyIds = [];

                // Batch through 100 at a time
                foreach (array_chunk($shopifyIds, 100) as $ids) {
                    $args = ['first' => 100, 'query' => 'id:' . implode(' OR id:', $ids)];
                    if ($startCursor) {
                        $args['after'] = $startCursor;
                    }

                    // Check with the Shopify API to see if these products still exist
                    $query = (new GqlQuery('products'))
                    ->setArguments($args)
                    ->setSelectionSet([
                        (new GqlQuery('edges'))
                            ->setSelectionSet([
                                (new GqlQuery('node'))
                                    ->setSelectionSet([
                                        'id',
                                    ]),
                            ]),
                        (new GqlQuery('pageInfo'))
                            ->setSelectionSet([
                                'hasNextPage',
                                'endCursor',
                            ]),
                    ]);
                    $response = self::getInstance()->getApi()->query($query);

                    if (!$response) {
                        continue;
                    }

                    $startCursor = $response['pageInfo']['endCursor'];

                    $savedShopifyIds = array_merge($savedShopifyIds, array_map(static function($edge) {
                        return str_replace('gid://shopify/Product/', '', $edge['node']['id']);
                    }, $response['edges']));
                }

                $deleteIds = array_diff($shopifyIds, $savedShopifyIds);

                if (!empty($deleteIds)) {
                    foreach ($deleteIds as $deleteId) {
                        $element = ArrayHelper::firstWhere($shopifyProductElementsMissingData, 'shopifyId', $deleteId);
                        Craft::$app->getElements()->deleteElementById($element['id'], Product::class, null, true);
                    }
                }

                if (Craft::$app instanceof ConsoleApplication) {
                    Console::stdout("done\n", Console::FG_GREEN);
                }
            }
        });
    }

    /**
     * @inheritdoc
     */
    public function getCpNavItem(): ?array
    {
        $ret = parent::getCpNavItem();
        $ret['label'] = Craft::t('shopify', 'Shopify');

        $connected = Plugin::getInstance()->getApi()->connect();

        $ret['subnav']['products'] = [
            'label' => Craft::t('shopify', 'Products'),
            'url' => 'shopify/products',
        ];

        $ret['subnav']['settings'] = [
            'label' => Craft::t('shopify', 'Settings'),
            'url' => 'shopify/settings',
        ];

        if ($connected) {
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
