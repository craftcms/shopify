# WIP Release Notes for Shopify 8.0

> [!IMPORTANT]
> If you change the **Additional Features** or **Custom Scopes** settings after the app is already authorized, you must update the scopes in your Shopify app configuration and then re-authorize the app.

- Added support for syncing product translations from Shopify. ([#215](https://github.com/craftcms/shopify/issues/215))
- It’s now possible to view the required API scopes in the plugin settings.
- It’s now possible to extend the API scopes with opt-in additional features and custom scopes.
- It’s now possible to customize the Shopify API context before and after initialization via new events.
- Added `craft\shopify\controllers\SettingsController::actionGetScopes()`.
- Added `craft\shopify\events\DefineInitializeApiContextEvent`.
- Added `craft\shopify\models\Settings::REQUIRED_SCOPES`.
- Added `craft\shopify\models\Settings::getAdditionalFeatures()`.
- Added `craft\shopify\models\Settings::getAdditionalFeaturesOptions()`.
- Added `craft\shopify\models\Settings::getCustomScopes()`.
- Added `craft\shopify\models\Settings::getScopes()`.
- Added `craft\shopify\models\Settings::setAdditionalFeatures()`.
- Added `craft\shopify\models\Settings::setCustomScopes()`.
- Added `craft\shopify\services\Api::EVENT_AFTER_INITIALIZE_API_CONTEXT`.
- Added `craft\shopify\services\Api::EVENT_DEFINE_INITIALIZE_API_CONTEXT`.
- Added `craft\shopify\services\Api::getShopLocalesGql()`.
- Fixed a bug where validation errors for the "Context Pricing Countries" setting weren't displaying correctly.