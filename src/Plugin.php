<?php

declare(strict_types=1);

namespace CraftCms\Shopify;

use Craft;
use craft\console\Application as ConsoleApplication;
use craft\db\Query;
use craft\events\DefineFieldLayoutFieldsEvent;
use craft\events\RegisterComponentTypesEvent;
use craft\events\RegisterGqlQueriesEvent;
use craft\events\RegisterGqlSchemaComponentsEvent;
use craft\events\RegisterGqlTypesEvent;
use craft\feedme\events\RegisterFeedMeFieldsEvent;
use craft\fields\Link;
use craft\helpers\ArrayHelper;
use craft\helpers\Console;
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
use craft\shopify\utilities\Sync;
use craft\shopify\web\twig\CraftVariableBehavior;
use craft\shopify\webhooks\WebhookRegistry;
use craft\web\twig\variables\CraftVariable;
use CraftCms\Cms\Cp\Data\NavItem;
use CraftCms\Cms\Plugin\Plugin as BasePlugin;
use CraftCms\Cms\ProjectConfig\ProjectConfig;
use CraftCms\Cms\Route\Routes;
use CraftCms\Shopify\Api\Api;
use CraftCms\Shopify\BulkOperation\BulkOperations;
use CraftCms\Shopify\Console\Commands\ApiQueryCommand;
use CraftCms\Shopify\Console\Commands\DataResetCommand;
use CraftCms\Shopify\Console\Commands\ResaveProductsCommand;
use CraftCms\Shopify\Console\Commands\SyncAllCommand;
use CraftCms\Shopify\Console\Commands\SyncProductsCommand;
use CraftCms\Shopify\Product\Products;
use CraftCms\Shopify\Store\Store;
use GraphQL\Query as GqlQuery;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Http\RedirectResponse;
use yii\base\Event;

use function CraftCms\Cms\cp_url;
use function CraftCms\Cms\currentUser;
use function CraftCms\Cms\t;

/**
 * @method Settings getSettings()
 */
class Plugin extends BasePlugin
{
    public const string PC_PATH_PRODUCT_FIELD_LAYOUTS = 'shopify.productFieldLayout';

    public string $schemaVersion = '9.0.0.0';

    public string $minVersionRequired = '8.2.1';

    public bool $hasCpSettings = true;

    public bool $hasCpSection = true;

    protected array $commands = [
        ApiQueryCommand::class,
        DataResetCommand::class,
        ResaveProductsCommand::class,
        SyncAllCommand::class,
        SyncProductsCommand::class,
    ];

    #[\Override]
    protected static function createSettings(): Settings
    {
        return new Settings();
    }

    #[\Override]
    public function getSettingsResponse(): RedirectResponse
    {
        return redirect(cp_url('shopify/settings'));
    }

    #[\Override]
    public function getReadOnlySettingsResponse(): RedirectResponse
    {
        return redirect(cp_url('shopify/settings'));
    }

    #[\Override]
    public function register(): void
    {
        $this->app->singleton(Settings::class, fn() => $this->getSettings());

        // Shopify can't send a CSRF token with its webhooks.
        $routes = $this->app->make(Routes::class);
        PreventRequestForgery::except([
            'shopify/webhook/handle',
            $routes->actionTriggerUriPrefix() . '/shopify/webhook',
            $routes->actionTriggerUriPrefix() . '/shopify/webhook/handle',
            $routes->cpActionTriggerUriPrefix() . '/shopify/webhook',
            $routes->cpActionTriggerUriPrefix() . '/shopify/webhook/handle',
        ]);
    }

    public function boot(ProjectConfig $projectConfig, Products $products, Api $api): void
    {
        $this->registerElementTypes();
        $this->registerUtilityTypes();
        $this->registerFieldTypes();
        $this->registerFieldLayoutElements();
        $this->registerLinkTypes();
        $this->registerVariables();
        $this->registerGarbageCollection();
        $this->registerFeedMeEvents();
        $this->registerGqlInterfaces();
        $this->registerGqlQueries();
        $this->registerGqlComponents();

        $projectConfig->onAdd(self::PC_PATH_PRODUCT_FIELD_LAYOUTS, $products->handleChangedFieldLayout(...))
            ->onUpdate(self::PC_PATH_PRODUCT_FIELD_LAYOUTS, $products->handleChangedFieldLayout(...))
            ->onRemove(self::PC_PATH_PRODUCT_FIELD_LAYOUTS, $products->handleDeletedFieldLayout(...));

        foreach ($api->getWebhookTopics() as $topic) {
            WebhookRegistry::addHandler($topic, new Webhook());
        }
    }

    public function getBulkOperations(): BulkOperations
    {
        return $this->app->make(BulkOperations::class);
    }

    public function getApi(): Api
    {
        return $this->app->make(Api::class);
    }

    public function getProducts(): Products
    {
        return $this->app->make(Products::class);
    }

    public function getStore(): Store
    {
        return $this->app->make(Store::class);
    }

    #[\Override]
    public function getCpNavItem(): NavItem
    {
        $subnav = [
            'products' => new NavItem()
                ->label(t('Products', category: 'shopify'))
                ->url('shopify/products'),
            'settings' => new NavItem()
                ->label(t('Settings', category: 'shopify'))
                ->url('shopify/settings'),
        ];

        if (currentUser()?->isAdmin() && $this->getApi()->connect()) {
            $subnav['webhooks'] = new NavItem()
                ->label(t('Webhooks', category: 'shopify'))
                ->url('shopify/webhooks');
        }

        return parent::getCpNavItem()
            ->label(t('Shopify', category: 'shopify'))
            ->subnav($subnav);
    }

    private function registerFeedMeEvents(): void
    {
        $feedMePlugin = Craft::$app->getPlugins()->getPlugin('feed-me');
        if (!class_exists(\craft\feedme\services\Fields::class) || !$feedMePlugin) {
            return;
        }

        Event::on(\craft\feedme\services\Fields::class, \craft\feedme\services\Fields::EVENT_REGISTER_FEED_ME_FIELDS, function(RegisterFeedMeFieldsEvent $event) {
            $event->fields[] = FeedMeProductsField::class;
        });
    }

    private function registerUtilityTypes(): void
    {
        Event::on(Utilities::class, Utilities::EVENT_REGISTER_UTILITIES, function(RegisterComponentTypesEvent $event) {
            $event->types[] = Sync::class;
        });
    }

    private function registerElementTypes(): void
    {
        Event::on(Elements::class, Elements::EVENT_REGISTER_ELEMENT_TYPES, static function(RegisterComponentTypesEvent $e) {
            $e->types[] = Product::class;
        });
    }

    private function registerGqlInterfaces(): void
    {
        Event::on(Gql::class, Gql::EVENT_REGISTER_GQL_TYPES, static function(RegisterGqlTypesEvent $event) {
            $event->types[] = GqlProductInterface::class;
        });
    }

    private function registerGqlQueries(): void
    {
        Event::on(Gql::class, Gql::EVENT_REGISTER_GQL_QUERIES, static function(RegisterGqlQueriesEvent $event) {
            $event->queries = array_merge(
                $event->queries,
                GqlProductQueries::getQueries(),
            );
        });
    }

    private function registerGqlComponents(): void
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

    private function registerFieldTypes(): void
    {
        Event::on(Fields::class, Fields::EVENT_REGISTER_FIELD_TYPES, static function(RegisterComponentTypesEvent $event) {
            $event->types[] = ProductsField::class;
        });
    }

    private function registerFieldLayoutElements(): void
    {
        Event::on(FieldLayout::class, FieldLayout::EVENT_DEFINE_NATIVE_FIELDS, static function(DefineFieldLayoutFieldsEvent $e) {
            /** @var FieldLayout $fieldLayout */
            $fieldLayout = $e->sender;

            if ($fieldLayout->type === Product::class) {
                $e->fields[] = VariantsField::class;
                $e->fields[] = OptionsField::class;
                $e->fields[] = MetafieldsField::class;
                $e->fields[] = MediaField::class;
            }
        });
    }

    private function registerLinkTypes(): void
    {
        Event::on(Link::class, Link::EVENT_REGISTER_LINK_TYPES, function(RegisterComponentTypesEvent $event) {
            $event->types[] = ProductLinkType::class;
        });
    }

    private function registerVariables(): void
    {
        Event::on(CraftVariable::class, CraftVariable::EVENT_INIT, static function(Event $event) {
            $event->sender->attachBehavior('shopify', CraftVariableBehavior::class);
        });
    }

    private function registerGarbageCollection(): void
    {
        Event::on(Gc::class, Gc::EVENT_RUN, function(Event $event) {
            if (Craft::$app instanceof ConsoleApplication) {
                Console::stdout('    > purging syncs ... ');
            }

            $this->getBulkOperations()->purgeBulkOperations();

            if (Craft::$app instanceof ConsoleApplication) {
                Console::stdout("done\n", Console::FG_GREEN);
                Console::stdout('    > deleting partial product elements ... ');
            }

            /** @var Gc $gc */
            $gc = $event->sender;
            $gc->deletePartialElements(Product::class, 'shopify_products', 'id');

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

            if (empty($shopifyIds)) {
                return;
            }

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
                $response = $this->getApi()->query($query);

                if (!$response) {
                    continue;
                }

                $startCursor = $response['pageInfo']['endCursor'];

                $savedShopifyIds = array_merge($savedShopifyIds, array_map(static function($edge) {
                    return str_replace('gid://shopify/Product/', '', $edge['node']['id']);
                }, $response['edges']));
            }

            foreach (array_diff($shopifyIds, $savedShopifyIds) as $deleteId) {
                $element = ArrayHelper::firstWhere($shopifyProductElementsMissingData, 'shopifyId', $deleteId);
                Craft::$app->getElements()->deleteElementById($element['id'], Product::class, null, true);
            }

            if (Craft::$app instanceof ConsoleApplication) {
                Console::stdout("done\n", Console::FG_GREEN);
            }
        });
    }
}
