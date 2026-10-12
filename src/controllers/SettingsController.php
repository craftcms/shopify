<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\shopify\controllers;

use Craft;
use craft\helpers\StringHelper;
use craft\helpers\UrlHelper;
use craft\queue\jobs\ResaveElements;
use craft\shopify\elements\Product;
use craft\shopify\models\Settings;
use craft\shopify\Plugin;
use craft\web\Controller;
use CraftCms\Cms\Cms;
use CraftCms\Cms\Cp\SelectOptions;
use CraftCms\Cms\Http\Responses\CpScreenResponse;
use CraftCms\Cms\Ui\Controls\Choice;
use CraftCms\Cms\Ui\Controls\Combobox;
use CraftCms\Cms\Ui\Controls\FieldLayoutDesigner;
use CraftCms\Cms\Ui\Controls\Table;
use CraftCms\Cms\Ui\Controls\Text;
use CraftCms\Cms\Ui\Enums\ControlMode;
use CraftCms\Cms\Ui\Nodes\Callout;
use CraftCms\Cms\Ui\Nodes\Field;
use CraftCms\Cms\Ui\Nodes\Separator;
use CraftCms\Cms\Ui\Nodes\Tab;
use CraftCms\Cms\Ui\Ui;
use CraftCms\Cms\Ui\UiContext;
use CraftCms\Cms\Ui\UiPayload;
use CraftCms\Cms\Ui\UiResolver;
use yii\web\Response;

/**
 * The SettingsController handles modifying and saving the general settings.
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 3.0
 */
class SettingsController extends Controller
{
    /**
     * The row key of the product routing table.
     */
    private const ROUTING_ROW = 'product';

    /**
     * @inheritdoc
     */
    public function beforeAction($action): bool
    {
        if (!parent::beforeAction($action)) {
            return false;
        }

        // Admins can view settings even when allowAdminChanges is false (read-only mode)
        $this->requireAdmin(false);

        return true;
    }

    /**
     * Display a form to allow an administrator to update plugin settings.
     *
     * @return Response
     */
    public function actionIndex(?Settings $settings = null): Response
    {
        if ($settings == null) {
            $settings = Plugin::getInstance()->getSettings();
        }

        $readOnly = $this->isReadOnly();
        $payload = $this->resolveUi($settings, $this->initialValues($settings), $readOnly);

        $screen = (new CpScreenResponse())
            ->title(Craft::t('shopify', 'Settings'))
            ->selectedSubnavItem('settings')
            ->redirectUrl('shopify/settings')
            ->ui($payload, props: $readOnly ? [] : [
                'submit' => [
                    'method' => 'post',
                    'url' => UrlHelper::actionUrl('shopify/settings/save-settings'),
                ],
                'refreshUrl' => UrlHelper::actionUrl('shopify/settings/render-ui'),
            ]);

        return $this->response->setIlluminateResponse($screen->toResponse(request()));
    }

    /**
     * Re-resolves the settings UI for the posted values, so derived values like the scopes stay current.
     *
     * @return Response
     */
    public function actionRenderUi(): Response
    {
        $this->requireAdmin();
        $this->requireAcceptsJson();
        $this->requirePostRequest();

        $values = (array)$this->request->getRequiredBodyParam('values');
        $scope = (array)$this->request->getBodyParam('scope', []);

        $settings = new Settings();
        $settings->setAdditionalFeatures($this->normalizeAdditionalFeatures($values['settings']['additionalFeatures'] ?? []));
        $settings->setCustomScopes((string)($values['settings']['customScopes'] ?? ''));
        $values['scopes'] = $settings->getScopes();

        return $this->asJson([
            'ui' => $this->resolveUi(Plugin::getInstance()->getSettings(), $values, false)->forScope($scope),
        ]);
    }

    /**
     * Save the settings.
     *
     * @return ?Response
     */
    public function actionSaveSettings(): ?Response
    {
        $this->requireAdmin();
        $this->requirePostRequest();
        $settings = (array)Craft::$app->getRequest()->getParam('settings');
        $routing = Craft::$app->getRequest()->getParam('routing')[self::ROUTING_ROW] ?? [];
        $plugin = Plugin::getInstance();
        /** @var Settings $pluginSettings */
        $pluginSettings = $plugin->getSettings();
        $originalUriFormat = $pluginSettings->uriFormat;

        $settings['additionalFeatures'] = $this->normalizeAdditionalFeatures($settings['additionalFeatures'] ?? []);
        $settings['uriFormat'] = $routing['uriFormat'] ?? '';
        // Not shown in headless mode
        if (isset($routing['template'])) {
            $settings['template'] = $routing['template'];
        }

        $settingsSuccess = Craft::$app->getPlugins()->savePluginSettings($plugin, $settings);

        $fieldLayout = Craft::$app->getFields()->assembleLayoutFromPost();
        $fieldLayout->type = Product::class;

        $projectConfig = Craft::$app->getProjectConfig();
        $uid = StringHelper::UUID();
        $fieldLayoutConfig = $fieldLayout->getConfig();
        $projectConfig->set(Plugin::PC_PATH_PRODUCT_FIELD_LAYOUTS, [$uid => $fieldLayoutConfig], 'Save the Shopify product field layout');

        $pluginSettings->setProductFieldLayout($fieldLayout);

        if (!$settingsSuccess) {
            if (Craft::$app->getRequest()->getAcceptsJson()) {
                return $this->asModelFailure(
                    $pluginSettings,
                    Craft::t('shopify', 'Couldn’t save settings.'),
                    'settings',
                );
            }

            // Re-render the screen so its UI carries the validation errors.
            $this->setFailFlash(Craft::t('shopify', 'Couldn’t save settings.'));

            return $this->actionIndex($pluginSettings);
        }

        // Resave all products if the URI format changed
        if ($originalUriFormat != $settings['uriFormat']) {
            Craft::$app->getQueue()->push(new ResaveElements([
                'elementType' => Product::class,
                'criteria' => [
                    'siteId' => '*',
                    'unique' => true,
                    'status' => null,
                ],
            ]));
        }

        if (Craft::$app->getRequest()->getAcceptsJson()) {
            return $this->asModelSuccess(
                $pluginSettings,
                Craft::t('shopify', 'Settings saved.'),
                'settings',
            );
        }

        // Yii flags AJAX redirects with a header rather than `Location`, which Inertia can't follow.
        $this->setSuccessFlash(Craft::t('shopify', 'Settings saved.'));

        return $this->response->setIlluminateResponse(
            redirect(UrlHelper::cpUrl($this->getPostedRedirectUrl() ?? 'shopify/settings')),
        );
    }

    private function isReadOnly(): bool
    {
        return !Cms::config()->allowAdminChanges;
    }

    private function headlessMode(): bool
    {
        return Cms::config()->headlessMode;
    }

    /**
     * @return array<string, mixed>
     */
    private function initialValues(Settings $settings): array
    {
        $fieldLayout = $settings->getProductFieldLayout();

        return [
            'settings' => [
                'apiVersion' => $settings->getApiVersion(false),
                'clientId' => $settings->getClientId(false),
                'clientSecret' => $settings->getClientSecret(false),
                'hostName' => $settings->getHostName(false),
                'contextualPricingCountries' => $settings->getContextualPricingCountries(false),
                'additionalFeatures' => $settings->getAdditionalFeatures(),
                'customScopes' => $settings->getCustomScopes(false),
            ],
            'scopes' => $settings->getScopes(),
            'authUrl' => $settings->getAuthUrl(),
            'routing' => [
                self::ROUTING_ROW => array_filter([
                    'uriFormat' => $settings->uriFormat,
                    'template' => $this->headlessMode() ? null : $settings->template,
                ], fn($value) => $value !== null),
            ],
            'fieldLayout' => [
                'id' => $fieldLayout->id,
                'uid' => $fieldLayout->uid,
                ...($fieldLayout->getConfig() ?? []),
            ],
        ];
    }

    /**
     * @param array<string, mixed> $values
     */
    private function resolveUi(Settings $settings, array $values, bool $readOnly): UiPayload
    {
        $errors = [];
        foreach ($settings->errors()->getMessages() as $attribute => $messages) {
            $root = explode('.', $attribute)[0];
            $path = in_array($root, ['uriFormat', 'template'], true)
                ? 'routing.' . self::ROUTING_ROW . ".$attribute"
                : "settings.$attribute";
            $errors[$path] = $messages;
        }

        return app(UiResolver::class)->resolve($this->settingsUi($settings, $readOnly), new UiContext(
            values: $values,
            errors: $errors,
            mode: $readOnly ? ControlMode::ReadOnly : ControlMode::Editable,
            refreshable: !$readOnly,
        ));
    }

    private function settingsUi(Settings $settings, bool $readOnly): Ui
    {
        $api = Plugin::getInstance()->getApi();
        $apiVersions = $api->getSupportedApiVersions();
        $envTriggers = SelectOptions::getEnvTextExpanderTriggers();
        $envTip = Craft::t('app', 'Type `$` to choose an environment variable.');
        $envText = fn(string $attribute, bool $reactive = false) => Field::make(
            $settings->getAttributeLabel($attribute),
            Text::make("settings.$attribute")->textExpanderTriggers($envTriggers)->reactive($reactive),
        )->tip($envTip);

        $apiVersionOptions = array_map(fn(string $version) => ['label' => $version, 'value' => $version], $apiVersions);

        $ui = Ui::make()->when($readOnly, fn(Ui $ui) => $ui->add(
            Callout::make('read-only-notice', Craft::t('app', 'Changes to these settings aren’t permitted in this environment.'))
                ->variant('warning'),
        ));

        return $ui->add(
            Tab::make('api', Craft::t('shopify', 'API Connection'), [
                Field::make(
                    $settings->getAttributeLabel('apiVersion'),
                    Combobox::make('settings.apiVersion')
                        ->options(array_merge($apiVersionOptions, SelectOptions::getEnvOptions($apiVersions)))
                        ->showAllOnEmpty(),
                )
                    ->instructions(Craft::t('shopify', 'Supported API versions: {versions}', ['versions' => implode(', ', $apiVersions)]))
                    ->tip(Craft::t('app', 'This can be set to an environment variable.')),
                $envText('clientId'),
                $envText('clientSecret'),
                $envText('hostName')
                    ->instructions(Craft::t('shopify', 'The Shopify store hostname.')),
                $envText('contextualPricingCountries')
                    ->instructions(Craft::t('shopify', 'A comma separated list of country codes used to return contextual pricing.')),
                Separator::make('scopes-separator'),
                Field::make($settings->getAttributeLabel('scopes'), Text::make('scopes')->mode(ControlMode::ReadOnly)->monospace())
                    ->instructions(Craft::t('shopify', 'API scopes required for your app integration, including additional features and custom scopes.'))
                    ->tip(Craft::t('shopify', 'Copy these scopes into your Shopify app’s configuration in the Dev Dashboard to ensure your integration works correctly.')),
                Field::make(
                    $settings->getAttributeLabel('additionalFeatures'),
                    Choice::make('settings.additionalFeatures')
                        ->options(array_values(array_map(
                            fn(array $option) => ['label' => $option['label'], 'value' => $option['value']],
                            $settings->getAdditionalFeaturesOptions(),
                        )))
                        ->multiple()
                        ->allOption()
                        ->reactive(),
                ),
                $envText('customScopes', reactive: true)
                    ->instructions(Craft::t('shopify', 'A comma separated list of custom scopes to add to the API requests.')),
                Separator::make('auth-url-separator'),
                Field::make($settings->getAttributeLabel('authUrl'), Text::make('authUrl')->mode(ControlMode::ReadOnly)->monospace())
                    ->instructions(Craft::t('shopify', 'The URL of your Shopify app in the Dev Dashboard. This is automatically generated from your CP URL.'))
                    ->warning(!$api->connect() ? Craft::t('shopify', 'Unable to connect to custom app. Syncing will be unavailable until the app has been authorized.') : null),
            ]),
            Tab::make('products', Craft::t('shopify', 'Products'), [
                Field::make(Craft::t('shopify', 'Routing Settings'), $this->routingTable())
                    ->instructions(Craft::t('shopify', 'Configure the product’s front-end routing settings.')),
                Field::make(Craft::t('app', 'Field Layout'), FieldLayoutDesigner::make('fieldLayout')
                    ->elementType(Product::class)),
            ]),
        );
    }

    private function routingTable(): Table
    {
        return Table::make('routing')
            ->keyed()
            ->columns(array_filter([
                'uriFormat' => [
                    'type' => 'singleline',
                    'heading' => Craft::t('shopify', 'Product URI Format'),
                    'info' => Craft::t('shopify', 'What product URIs should look like.'),
                    'placeholder' => Craft::t('shopify', 'Leave blank if products don’t have URLs'),
                    'code' => true,
                ],
                'template' => $this->headlessMode() ? null : [
                    'type' => 'template',
                    'heading' => Craft::t('app', 'Template'),
                    'info' => Craft::t('shopify', 'Which template should be loaded when a product’s URL is requested.'),
                    'options' => SelectOptions::getTemplateSuggestions(),
                    'code' => true,
                ],
            ]));
    }

    /**
     * Expands the “All” option to every feature handle.
     *
     * @return string[]
     */
    private function normalizeAdditionalFeatures(mixed $value): array
    {
        if ($value === Choice::ALL_VALUE) {
            return array_keys((new Settings())->getAdditionalFeaturesOptions());
        }

        return array_values(array_filter((array)$value));
    }
}
