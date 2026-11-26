<?php

declare(strict_types=1);

namespace CraftCms\Shopify;

use Closure;
use craft\feedme\events\RegisterFeedMeFieldsEvent;
use craft\feedme\services\Fields as FeedMeFields;
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
use craft\shopify\webhooks\WebhookRegistry;
use CraftCms\Cms\Cp\Data\NavItem;
use CraftCms\Cms\FieldLayout\FieldLayout;
use CraftCms\Cms\GarbageCollection\Actions\DeletePartialElements;
use CraftCms\Cms\GarbageCollection\Events\RunningGarbageCollection;
use CraftCms\Cms\Gql\Events\GqlSchemaComponentsResolving;
use CraftCms\Cms\Plugin\Plugin as BasePlugin;
use CraftCms\Cms\Plugin\Plugins;
use CraftCms\Cms\ProjectConfig\ProjectConfig;
use CraftCms\Cms\Route\Routes;
use CraftCms\Cms\Support\Typecast;
use CraftCms\Cms\Twig\Variables\CraftVariable;
use CraftCms\Shopify\Api\Api;
use CraftCms\Shopify\BulkOperation\BulkOperations;
use CraftCms\Shopify\Console\Commands\ApiQueryCommand;
use CraftCms\Shopify\Console\Commands\DataResetCommand;
use CraftCms\Shopify\Console\Commands\ResaveProductsCommand;
use CraftCms\Shopify\Console\Commands\SyncAllCommand;
use CraftCms\Shopify\Console\Commands\SyncProductsCommand;
use CraftCms\Shopify\Database\Table;
use CraftCms\Shopify\GarbageCollection\Actions\DeleteRemovedProducts;
use CraftCms\Shopify\GarbageCollection\Actions\PurgeBulkOperations;
use CraftCms\Shopify\Product\Elements\Product;
use CraftCms\Shopify\Product\Policies\ProductPolicy;
use CraftCms\Shopify\Product\Products;
use CraftCms\Shopify\Product\Queries\ProductQuery;
use CraftCms\Shopify\Store\Store;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use yii\base\Event as YiiEvent;

use function CraftCms\Cms\cp_url;
use function CraftCms\Cms\currentUser;
use function CraftCms\Cms\t;

/**
 * @method Settings getSettings()
 */
class Plugin extends BasePlugin
{
    public const string PC_PATH_PRODUCT_FIELD_LAYOUTS = 'shopify.productFieldLayout';

    public string $schemaVersion = '9.0.0.2';

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

    protected array $elementTypes = [
        Product::class,
    ];

    protected array $fieldTypes = [
        ProductsField::class,
    ];

    protected array $linkTypes = [
        ProductLinkType::class,
    ];

    protected array $utilities = [
        Sync::class,
    ];

    protected array $gqlTypes = [
        GqlProductInterface::class,
    ];

    protected array $gqlQueries = [
        GqlProductQueries::class,
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

    public function boot(ProjectConfig $projectConfig, Products $products, Api $api, Plugins $plugins): void
    {
        $this->registerVariables();
        $this->registerFeedMeFields($plugins);

        Gate::policy(Product::class, ProductPolicy::class);

        Event::listen(function(GqlSchemaComponentsResolving $event): void {
            $event->queries[t('Shopify Products', category: 'shopify')] = [
                (new Product())->getGqlTypeName() . ':read' => ['label' => t('View products', category: 'shopify')],
            ];
        });

        Event::listen(function(RunningGarbageCollection $event): void {
            $event->garbageCollection->runActions([
                PurgeBulkOperations::class,
                [DeletePartialElements::class, ['elementType' => Product::class, 'table' => Table::PRODUCTS]],
                DeleteRemovedProducts::class,
            ]);
        });

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

    #[\Override]
    protected function getNativeFields(): Closure
    {
        return function(FieldLayout $fieldLayout, array $fields): array {
            if ($fieldLayout->type === Product::class) {
                $fields[] = VariantsField::class;
                $fields[] = OptionsField::class;
                $fields[] = MetafieldsField::class;
                $fields[] = MediaField::class;
            }

            return $fields;
        };
    }

    private function registerVariables(): void
    {
        $plugin = $this;
        CraftVariable::macro('shopify', fn() => $plugin);

        CraftVariable::macro('shopifyProducts', function(array $criteria = []): ProductQuery {
            $query = Product::find();
            Typecast::configure($query, $criteria);

            return $query;
        });
    }

    /**
     * Feed Me doesn’t support Craft 6 yet, so this is untested and stays on its Yii event.
     */
    private function registerFeedMeFields(Plugins $plugins): void
    {
        if (!class_exists(FeedMeFields::class) || !$plugins->getPlugin('feed-me')) {
            return;
        }

        YiiEvent::on(FeedMeFields::class, FeedMeFields::EVENT_REGISTER_FEED_ME_FIELDS, function(RegisterFeedMeFieldsEvent $event) {
            $event->fields[] = FeedMeProductsField::class;
        });
    }
}
