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

        $readOnly = !Craft::$app->getConfig()->getGeneral()->allowAdminChanges;
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
                'static' => $readOnly,
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

            Cp::fieldHtml(Cp::fieldLayoutDesignerHtml($settings->getProductFieldLayout(), ['disabled' => $readOnly]), [
                'label' => Craft::t('app', 'Field Layout'),
                'disabled' => $readOnly,
            ]) .

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
                    'disabled' => $readOnly,
                ]) .

                Cp::autosuggestFieldHtml([
                    'label' => $settings->getAttributeLabel('clientId'),
                    'id' => 'clientId',
                    'name' => 'settings[clientId]',
                    'value' => $settings->getClientId(false),
                    'errors' => $settings->getErrors('clientId'),
                    'suggestEnvVars' => true,
                    'disabled' => $readOnly,
                ]) .

                Cp::autosuggestFieldHtml([
                    'label' => $settings->getAttributeLabel('clientSecret'),
                    'id' => 'clientSecret',
                    'name' => 'settings[clientSecret]',
                    'value' => $settings->getClientSecret(false),
                    'errors' => $settings->getErrors('clientSecret'),
                    'suggestEnvVars' => true,
                    'disabled' => $readOnly,
                ]) .

                Cp::autosuggestFieldHtml([
                    'label' => $settings->getAttributeLabel('hostName'),
                    'instructions' => Craft::t('shopify', 'The Shopify store hostname.'),
                    'id' => 'hostName',
                    'name' => 'settings[hostName]',
                    'value' => $settings->getHostName(false),
                    'errors' => $settings->getErrors('hostName'),
                    'suggestEnvVars' => true,
                    'disabled' => $readOnly,
                ]) .

                Cp::autosuggestFieldHtml([
                    'label' => $settings->getAttributeLabel('contextualPricingCountries'),
                    'instructions' => Craft::t('shopify', 'A comma separated list of country codes used to return contextual pricing.'),
                    'id' => 'contextualPricingCountries',
                    'name' => 'settings[contextualPricingCountries]',
                    'value' => $settings->getContextualPricingCountries(false),
                    'errors' => $settings->getErrors('contextualPricingCountries'),
                    'suggestEnvVars' => true,
                    'disabled' => $readOnly,
                ]) .

                Html::tag('hr') .

                Cp::fieldHtml(
                    Cp::renderTemplate('_includes/forms/copytext.twig', $authUrlFieldConfig),
                    $authUrlFieldConfig
                ) .

            Html::endTag('div')
        ;

        $screen = $this->asCpScreen()
            ->title(Craft::t('shopify', 'Settings'))
            ->tabs([
                ['label' => Craft::t('shopify', 'API Connection'), 'url' => '#api'],
                ['label' => Craft::t('shopify', 'Products'), 'url' => '#products'],
            ])
            ->selectedSubnavItem('settings');

        if (!$readOnly) {
            $screen->action('shopify/settings/save-settings')
                ->redirectUrl('shopify/settings');
        } else {
            // @TODO remove when the plugin no longer support Craft 4
            if (method_exists(Cp::class, 'readOnlyNoticeHtml')) {
                $screen->noticeHtml(Cp::readOnlyNoticeHtml());
            }
        }

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
     * Save the settings.
     *
     * @return ?Response
     */
    public function actionSaveSettings(): ?Response
    {
        $this->requireAdmin();
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
