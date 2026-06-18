<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\shopify\controllers;

use Craft;
use craft\helpers\Cp;
use craft\helpers\Html;
use craft\helpers\StringHelper;
use craft\queue\jobs\ResaveElements;
use craft\shopify\elements\Product;
use craft\shopify\models\Settings;
use craft\shopify\Plugin;
use craft\web\Controller;
use craft\web\Response as CraftResponse;
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
     * @inheritdoc
     */
    public function beforeAction($action): bool
    {
        if (!parent::beforeAction($action)) {
            return false;
        }

        // Only administrators should be allowed to update plugin settings
        $this->requireAdmin();

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

        $headlessMode = Craft::$app->getConfig()->getGeneral()->headlessMode;

        $authUrlFieldConfig = [
            'label' => $settings->getAttributeLabel('authUrl'),
            'instructions' => Craft::t('shopify', 'The URL of your Shopify app in the Dev Dashboard. This is automatically generated from your CP URL.'),
            'id' => 'authUrl',
            'name' => 'settings[authUrl]',
            'value' => $settings->getAuthUrl(),
            'readonly' => true,
            'warning' => !Plugin::getInstance()->getApi()->getSession() ? Craft::t('shopify', 'Unable to connect to custom app. Syncing will be unavailable until the app has been authorized.') : null,
        ];

        $scopesFieldConfig = [
            'label' => $settings->getAttributeLabel('scopes'),
            'instructions' => Craft::t('shopify', 'API scopes required for your app integration, including additional features and custom scopes.'),
            'id' => 'scopes',
            'name' => 'settings[scopes]',
            'value' => $settings->getScopes(),
            'readonly' => true,
            'tip' => Craft::t('shopify', 'Copy these scopes into your Shopify app’s configuration in the Dev Dashboard to ensure your integration works correctly.'),
        ];

        $html = Html::beginTag('div', ['id' => 'products', 'class' => 'hidden']) .
            // Products tab has to go first because the routing table overrides the `settings` key
            Cp::editableTableFieldHtml([
                'label' => Craft::t('shopify', 'Routing Settings'),
                'instructions' => Craft::t('shopify', 'Configure the product’s front-end routing settings.'),
                'id' => 'routing',
                'name' => 'settings',
                'allowAdd' => false,
                'allowDelete' => false,
                'allowReorder' => false,
                'errors' => array_unique($settings->getErrors('routing')),
                'cols' => array_filter([
                    'uriFormat' => [
                        'type' => 'singleline',
                        'heading' => Craft::t('shopify', 'Product URI Format'),
                        'info' => Craft::t('shopify', 'What product URIs should look like.'),
                        'placeholder' => Craft::t('shopify', 'Leave blank if products don’t have URLs'),
                        'code' => true,
                    ],
                    'template' => $headlessMode ? [] : [
                        'type' => 'template',
                        'heading' => Craft::t('app', 'Template'),
                        'info' => Craft::t('shopify', 'Which template should be loaded when a product’s URL is requested.'),
                        'code' => true,
                    ],
                ]),
                'rows' => [
                    'routing' => [
                        'uriFormat' => [
                            'value' => $settings->uriFormat ?? null,
                            'hasErrors' => $settings->hasErrors('uriFormat'),
                        ],
                        'template' => $headlessMode ? [] : [
                            'value' => $settings->template ?? null,
                            'hasErrors' => $settings->hasErrors('template'),
                        ],
                    ],
                ],
            ]) .

            Cp::fieldLayoutDesignerHtml($settings->getProductFieldLayout()) .

            Html::endTag('div') .

            Html::beginTag('div', ['id' => 'api']) .

                Cp::autosuggestFieldHtml([
                    'first' => true,
                    'label' => $settings->getAttributeLabel('apiVersion'),
                    'instructions' => Craft::t('shopify', 'Supported API versions: {versions}', ['versions' => implode(', ', Plugin::getInstance()->getApi()->getSupportedApiVersions())]),
                    'id' => 'apiVersion',
                    'name' => 'settings[apiVersion]',
                    'value' => $settings->getApiVersion(false),
                    'errors' => $settings->getErrors('apiVersion'),
                    'suggestEnvVars' => true,
                    'autofocus' => true,
                ]) .

                Cp::autosuggestFieldHtml([
                    'label' => $settings->getAttributeLabel('clientId'),
                    'id' => 'clientId',
                    'name' => 'settings[clientId]',
                    'value' => $settings->getClientId(false),
                    'errors' => $settings->getErrors('clientId'),
                    'suggestEnvVars' => true,
                ]) .

                Cp::autosuggestFieldHtml([
                    'label' => $settings->getAttributeLabel('clientSecret'),
                    'id' => 'clientSecret',
                    'name' => 'settings[clientSecret]',
                    'value' => $settings->getClientSecret(false),
                    'errors' => $settings->getErrors('clientSecret'),
                    'suggestEnvVars' => true,
                ]) .

                Cp::autosuggestFieldHtml([
                    'label' => $settings->getAttributeLabel('hostName'),
                    'instructions' => Craft::t('shopify', 'The Shopify store hostname.'),
                    'id' => 'hostName',
                    'name' => 'settings[hostName]',
                    'value' => $settings->getHostName(false),
                    'errors' => $settings->getErrors('hostName'),
                    'suggestEnvVars' => true,
                ]) .

                Cp::autosuggestFieldHtml([
                    'label' => $settings->getAttributeLabel('contextualPricingCountries'),
                    'instructions' => Craft::t('shopify', 'A comma separated list of country codes used to return contextual pricing.'),
                    'id' => 'contextualPricingCountries',
                    'name' => 'settings[contextualPricingCountries]',
                    'value' => $settings->getContextualPricingCountries(false),
                    'errors' => $settings->getErrors('contextualPricingCountries'),
                    'suggestEnvVars' => true,
                ]) .

                Html::tag('hr') .

                Html::beginTag('div', ['id' => 'scopes-settings']) .

                    Cp::fieldHtml(
                        Cp::renderTemplate('_includes/forms/copytext.twig', $scopesFieldConfig),
                        $scopesFieldConfig
                    ) .

                    Cp::checkboxSelectFieldHtml([
                        'id' => 'additionalFeatures',
                        'label' => $settings->getAttributeLabel('additionalFeatures'),
                        'name' => 'settings[additionalFeatures]',
                        'options' => $settings->getAdditionalFeaturesOptions(),
                        'values' => $settings->getAdditionalFeatures(),
                        'showAllOption' => true,
                    ]) .

                    Cp::autosuggestFieldHtml([
                        'label' => $settings->getAttributeLabel('customScopes'),
                        'instructions' => Craft::t('shopify', 'A comma separated list of custom scopes to add to the API requests.'),
                        'id' => 'customScopes',
                        'name' => 'settings[customScopes]',
                        'value' => $settings->getCustomScopes(false),
                        'errors' => $settings->getErrors('customScopes'),
                        'suggestEnvVars' => true,
                    ]) .

                Html::endTag('div') .

                Html::tag('hr') .

                Cp::fieldHtml(
                    Cp::renderTemplate('_includes/forms/copytext.twig', $authUrlFieldConfig),
                    $authUrlFieldConfig
                ) .

            Html::endTag('div')
        ;

        $getScopesAction = 'shopify/settings/get-scopes';
        $js = <<<JS
            (() => {
                const scopesSettingsContainer = document.getElementById('scopes-settings');
                if (!scopesSettingsContainer) return;

                let debounceTimer;

                const updateScopes = () => {
                    const additionalFeatures = Array.from(
                        scopesSettingsContainer.querySelectorAll('input[name="settings[additionalFeatures][]"]:checked')
                    ).map(cb => cb.value).filter(Boolean);

                    const customScopesInput = document.getElementById('customScopes');
                    const scopesInput = document.getElementById('scopes');
                    if (!scopesInput) return;

                    Craft.sendActionRequest('POST', '$getScopesAction', {
                        data: {
                            additionalFeatures,
                            customScopes: customScopesInput?.value ?? '',
                        },
                    }).then(response => {
                        scopesInput.value = response.data.scopes;
                    }).catch(() => {
                        Craft.cp.displayError(Craft.t('shopify', 'Couldn't update scopes.'));
                    });
                };

                scopesSettingsContainer.addEventListener('change', (e) => {
                    if (e.target.name === 'settings[additionalFeatures][]' || e.target.name === 'settings[additionalFeatures]') {
                        // Defer so Craft's checkbox-select JS can toggle related checkboxes first
                        setTimeout(updateScopes, 0);
                    } else if (e.target.id === 'customScopes') {
                        clearTimeout(debounceTimer);
                        debounceTimer = setTimeout(updateScopes, 300);
                    }
                });

                scopesSettingsContainer.addEventListener('input', (e) => {
                    if (e.target.id === 'customScopes') {
                        clearTimeout(debounceTimer);
                        debounceTimer = setTimeout(updateScopes, 300);
                    }
                });
            })();
        JS;
        $this->getView()->registerJs($js);

        $screen = $this->asCpScreen()
            ->title(Craft::t('shopify', 'Settings'))
            ->tabs([
                ['label' => Craft::t('shopify', 'API Connection'), 'url' => '#api'],
                ['label' => Craft::t('shopify', 'Products'), 'url' => '#products'],
            ])
            ->action('shopify/settings/save-settings')
            ->redirectUrl('shopify/settings')
            ->selectedSubnavItem('settings');

        return $this->_screenContent($screen, $html);
    }

    /**
     * Render CP screen content across Craft 4/5.
     * @TODO remove when the plugin no longer supports Craft 4
     */
    private function _screenContent(CraftResponse $screen, string $html): Response
    {
        $method = !$screen->hasMethod('contentHtml') ? 'content' : 'contentHtml';
        return $screen->{$method}($html);
    }

    /**
     * Returns the combined scopes string for the given additional features and custom scopes.
     *
     * @return Response
     * @since 7.2.0
     */
    public function actionGetScopes(): Response
    {
        $this->requireAcceptsJson();
        $this->requirePostRequest();

        $request = Craft::$app->getRequest();
        $settings = new Settings();
        $settings->setAdditionalFeatures((array)$request->getBodyParam('additionalFeatures', []));
        $settings->setCustomScopes($request->getBodyParam('customScopes', ''));

        return $this->asJson([
            'scopes' => $settings->getScopes(),
        ]);
    }

    /**
     * Save the settings.
     *
     * @return ?Response
     */
    public function actionSaveSettings(): ?Response
    {
        $settings = Craft::$app->getRequest()->getParam('settings');
        $plugin = Plugin::getInstance();
        /** @var Settings $pluginSettings */
        $pluginSettings = $plugin->getSettings();
        $originalUriFormat = $pluginSettings->uriFormat;

        // Remove from editable table namespace
        $settings['uriFormat'] = $settings['routing']['uriFormat'];
        // Could be blank if in headless mode
        if (isset($settings['routing']['template'])) {
            $settings['template'] = $settings['routing']['template'];
        }
        unset($settings['routing']);

        $settingsSuccess = Craft::$app->getPlugins()->savePluginSettings($plugin, $settings);

        $fieldLayout = Craft::$app->getFields()->assembleLayoutFromPost();
        $fieldLayout->type = Product::class;

        $projectConfig = Craft::$app->getProjectConfig();
        $uid = StringHelper::UUID();
        $fieldLayoutConfig = $fieldLayout->getConfig();
        $projectConfig->set(Plugin::PC_PATH_PRODUCT_FIELD_LAYOUTS, [$uid => $fieldLayoutConfig], 'Save the Shopify product field layout');

        $pluginSettings->setProductFieldLayout($fieldLayout);

        if (!$settingsSuccess) {
            return $this->asModelFailure(
                $pluginSettings,
                Craft::t('shopify', 'Couldn’t save settings.'),
                'settings',
            );
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

        return $this->asModelSuccess(
            $pluginSettings,
            Craft::t('shopify', 'Settings saved.'),
            'settings',
        );
    }
}
