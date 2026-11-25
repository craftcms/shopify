<?php

declare(strict_types=1);

namespace CraftCms\Shopify\Http\Controllers;

use craft\shopify\elements\Product;
use craft\shopify\enums\ApiVersion;
use CraftCms\Cms\Config\GeneralConfig;
use CraftCms\Cms\Cp\SelectOptions;
use CraftCms\Cms\Element\Jobs\ResaveElements;
use CraftCms\Cms\Http\RespondsWithFlash;
use CraftCms\Cms\Http\Responses\CpScreenResponse;
use CraftCms\Cms\Plugin\Plugins;
use CraftCms\Cms\ProjectConfig\ProjectConfig;
use CraftCms\Cms\Support\Facades\Fields;
use CraftCms\Cms\Support\Str;
use CraftCms\Cms\Ui\Controls\Choice;
use CraftCms\Cms\Ui\Controls\Combobox;
use CraftCms\Cms\Ui\Controls\FieldLayoutDesigner;
use CraftCms\Cms\Ui\Controls\Text;
use CraftCms\Cms\Ui\Enums\ControlMode;
use CraftCms\Cms\Ui\Nodes\Field;
use CraftCms\Cms\Ui\Nodes\Heading;
use CraftCms\Cms\Ui\Nodes\Separator;
use CraftCms\Cms\Ui\Ui;
use CraftCms\Cms\Ui\UiContext;
use CraftCms\Cms\Ui\UiResolver;
use CraftCms\Shopify\Api\Api;
use CraftCms\Shopify\Plugin;
use CraftCms\Shopify\Settings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

use function CraftCms\Cms\action_url;
use function CraftCms\Cms\t;

/**
 * The Shopify settings screen, at `shopify/settings`.
 */
readonly class SettingsController
{
    use RespondsWithFlash;

    /**
     * The settings the screen edits. The rest of the posted values are the field layout and read-only values.
     */
    private const array SETTINGS = [
        'apiVersion',
        'clientId',
        'clientSecret',
        'hostName',
        'contextualPricingCountries',
        'additionalFeatures',
        'customScopes',
        'uriFormat',
        'template',
    ];

    public function __construct(
        private Plugin $plugin,
        private Settings $settings,
        private GeneralConfig $generalConfig,
        private UiResolver $uiResolver,
    ) {
    }

    public function edit(Api $api): CpScreenResponse
    {
        $readOnly = !$this->generalConfig->allowAdminChanges;
        $values = $this->values($this->settings);

        $ui = $this->uiResolver->resolve($this->buildUi($api->connect()), new UiContext(
            values: $values,
            mode: $readOnly ? ControlMode::ReadOnly : ControlMode::Editable,
            refreshable: !$readOnly,
        ));

        $response = new CpScreenResponse()
            ->title(t('Settings', category: 'shopify'))
            ->selectedSubnavItem('settings');

        if ($readOnly) {
            return $response->ui($ui);
        }

        return $response
            ->redirectUrl('shopify/settings')
            ->ui($ui, props: [
                'submit' => [
                    'method' => 'post',
                    'url' => action_url('shopify/settings/save-settings'),
                ],
                'refreshUrl' => action_url('shopify/settings/render-ui'),
            ]);
    }

    /**
     * Re-resolves the screen for the values being edited, so the scopes follow the additional features and
     * custom scopes.
     */
    public function renderUi(Request $request, Api $api): JsonResponse
    {
        $request->validate([
            'values' => ['required', 'array'],
        ]);

        $settings = new Settings();
        $settings->setAttributes($this->settingsInput($request->input('values')));

        $ui = $this->uiResolver->resolve($this->buildUi($api->connect()), new UiContext(
            values: array_replace($request->input('values'), ['scopes' => $settings->getScopes()]),
            mode: ControlMode::Editable,
            refreshable: true,
        ));

        return new JsonResponse(['ui' => $ui]);
    }

    public function update(Request $request, Plugins $plugins, ProjectConfig $projectConfig): Response
    {
        $originalUriFormat = $this->settings->uriFormat;
        $settingsSaved = $plugins->savePluginSettings($this->plugin, $this->settingsInput($request->all()));

        $fieldLayout = Fields::assembleLayoutFromPost();
        $fieldLayout->type = Product::class;
        $projectConfig->set(Plugin::PC_PATH_PRODUCT_FIELD_LAYOUTS, [Str::uuid()->toString() => $fieldLayout->getConfig()], 'Save the Shopify product field layout');
        $this->settings->setProductFieldLayout($fieldLayout);

        if (!$settingsSaved) {
            return $this->asModelFailure($this->settings, t('Couldn’t save settings.', category: 'shopify'), 'settings');
        }

        if ($originalUriFormat !== $this->settings->uriFormat) {
            dispatch(new ResaveElements(Product::class, [
                'siteId' => '*',
                'unique' => true,
                'status' => null,
            ]));
        }

        return $this->asModelSuccess($this->settings, t('Settings saved.', category: 'shopify'), 'settings');
    }

    /**
     * @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    private function settingsInput(array $input): array
    {
        $settings = array_intersect_key($input, array_flip(self::SETTINGS));
        $settings['additionalFeatures'] = array_values(array_filter((array)($settings['additionalFeatures'] ?? [])));

        return array_map(fn(mixed $value) => $value ?? '', $settings);
    }

    /**
     * @return array<string, mixed>
     */
    private function values(Settings $settings): array
    {
        $fieldLayout = $settings->getProductFieldLayout();

        return [
            'apiVersion' => $settings->getApiVersion(false),
            'clientId' => $settings->getClientId(false),
            'clientSecret' => $settings->getClientSecret(false),
            'hostName' => $settings->getHostName(false),
            'contextualPricingCountries' => $settings->getContextualPricingCountries(false),
            'scopes' => $settings->getScopes(),
            'additionalFeatures' => $settings->getAdditionalFeatures(),
            'customScopes' => $settings->getCustomScopes(false),
            'authUrl' => $settings->getAuthUrl(),
            'uriFormat' => $settings->uriFormat,
            'template' => $settings->template,
            'fieldLayout' => [
                'id' => $fieldLayout->id,
                'uid' => $fieldLayout->uid,
                ...($fieldLayout->getConfig() ?? []),
            ],
        ];
    }

    private function buildUi(bool $connected): Ui
    {
        $envTriggers = SelectOptions::getEnvTextExpanderTriggers();
        $envTip = t('Type <kbd>$</kbd> to choose an environment variable.');
        $envText = fn(string $path) => Text::make($path)->textExpanderTriggers($envTriggers);

        $productFields = [
            Field::make($this->settings->getAttributeLabel('uriFormat'), Text::make('uriFormat')->monospace())
                ->instructions(t('What product URIs should look like.', category: 'shopify'))
                ->tip(t('Leave blank if products don’t have URLs', category: 'shopify')),
        ];

        if (!$this->generalConfig->headlessMode) {
            $productFields[] = Field::make($this->settings->getAttributeLabel('template'), Combobox::make('template')
                ->options(SelectOptions::getTemplateSuggestions())
                ->showAllOnEmpty())
                ->instructions(t('Which template should be loaded when a product’s URL is requested.', category: 'shopify'));
        }

        $productFields[] = Separator::make('field-layout-separator');
        $productFields[] = Heading::make('field-layout-heading', t('Field Layout'))->level(3);
        $productFields[] = Field::make(null, FieldLayoutDesigner::make('fieldLayout')->elementType(Product::class));

        return Ui::make()
            ->addTab(t('API Connection', category: 'shopify'), [
                Field::make($this->settings->getAttributeLabel('apiVersion'), $envText('apiVersion'))
                    ->required()
                    ->instructions(t('Supported API versions: {versions}', [
                        'versions' => implode(', ', array_column(ApiVersion::cases(), 'value')),
                    ], category: 'shopify'))
                    ->tip($envTip),
                Field::make($this->settings->getAttributeLabel('clientId'), $envText('clientId'))
                    ->required()
                    ->tip($envTip),
                Field::make($this->settings->getAttributeLabel('clientSecret'), $envText('clientSecret'))
                    ->required()
                    ->tip($envTip),
                Field::make($this->settings->getAttributeLabel('hostName'), $envText('hostName'))
                    ->required()
                    ->instructions(t('The Shopify store hostname.', category: 'shopify'))
                    ->tip($envTip),
                Field::make($this->settings->getAttributeLabel('contextualPricingCountries'), $envText('contextualPricingCountries'))
                    ->instructions(t('A comma separated list of country codes used to return contextual pricing.', category: 'shopify'))
                    ->tip($envTip),
                Separator::make('scopes-separator'),
                Field::make($this->settings->getAttributeLabel('scopes'), Text::make('scopes')->mode(ControlMode::ReadOnly)->monospace()->selectOnFocus())
                    ->instructions(t('API scopes required for your app integration, including additional features and custom scopes.', category: 'shopify'))
                    ->tip(t('Copy these scopes into your Shopify app’s configuration in the Dev Dashboard to ensure your integration works correctly.', category: 'shopify')),
                Field::make($this->settings->getAttributeLabel('additionalFeatures'), Choice::make('additionalFeatures')
                    ->multiple()
                    ->options(array_map(fn(array $option) => [
                        'label' => $option['label'],
                        'value' => $option['value'],
                    ], array_values($this->settings->getAdditionalFeaturesOptions())))
                    ->reactive()),
                Field::make($this->settings->getAttributeLabel('customScopes'), $envText('customScopes')->reactive())
                    ->instructions(t('A comma separated list of custom scopes to add to the API requests.', category: 'shopify'))
                    ->tip($envTip),
                Separator::make('auth-url-separator'),
                Field::make($this->settings->getAttributeLabel('authUrl'), Text::make('authUrl')->mode(ControlMode::ReadOnly)->monospace()->selectOnFocus())
                    ->instructions(t('The URL of your Shopify app in the Dev Dashboard. This is automatically generated from your CP URL.', category: 'shopify'))
                    ->warning($connected ? null : t('Unable to connect to custom app. Syncing will be unavailable until the app has been authorized.', category: 'shopify')),
            ], uid: 'api')
            ->addTab(t('Products', category: 'shopify'), $productFields, uid: 'products');
    }
}
