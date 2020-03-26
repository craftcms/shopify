<?php

namespace shopify\fieldtypes;

use Craft;
use craft\base\Field;
use craft\base\ElementInterface;
use craft\base\PreviewableFieldInterface;
use shopify\Shopify;
use shopify\ShopifyAssets;

class CollectionFieldType extends Field implements PreviewableFieldInterface
{
    /**
     * @param mixed $value
     * @param ElementInterface $element
     * @return mixed
     */
    public function normalizeValue($value, ElementInterface $element = null)
    {
        if (is_array($value)) {
            return $value;
        }
        return json_decode($value);
    }

    // Static Methods
    // =========================================================================

    /**
     * Returns the display name of this class.
     *
     * @return string The display name of this class.
     */
    public static function displayName(): string
    {
        //category is the filename inside ./translations/en/
        return Craft::t('shopify', 'Shopify Collection');
    }

    /**
     * returns the template-partial an editor sees when editing plugin-content on a page
     *
     * @param mixed $value
     * @param ElementInterface $element
     * @return string
     * @throws \Twig_Error_Loader
     * @throws \yii\base\Exception
     */
    public function getInputHtml($value, ElementInterface $element = null): string
    {
        /** @var \shopify\models\Settings $settings */
        $settings = \shopify\Shopify::getInstance()->getSettings();

        $defaultOptions = [];
        $options = [];

        // SMART COLLECTIONS:
        $count = Shopify::getInstance()->service->getSmartCollectionsCount($defaultOptions);
        $collectionsData = Shopify::getInstance()->service->getCollections(
            $settings->allSmartCollectionsEndpoint,
            $defaultOptions
        );

        if ($smartCollections = $this->_getCollections($count, $collectionsData, 'smart_collections', $defaultOptions)) {
            $options = array_merge($options, $smartCollections);
        }

        // CUSTOM COLLECTIONS:
        $count = Shopify::getInstance()->service->getCustomCollectionsCount($defaultOptions);
        $collectionsData = Shopify::getInstance()->service->getCollections(
            $settings->allCustomCollectionsEndpoint,
            $defaultOptions
        );

        if ($customCollections = $this->_getCollections($count, $collectionsData, 'custom_collections', $defaultOptions)) {
            $options = array_merge($options, $customCollections);
        }

        Craft::$app->getView()->registerAssetBundle(ShopifyAssets::class);

        $wrapperClass = 'c-shopifyProductsPlugin';
        $instanceId = str_replace('.', '', uniqid('', true));
        return Craft::$app->getView()->renderTemplate('shopify/_select', [
            'wrapper_class' => $wrapperClass,
            'instance_wrapper_class' => $wrapperClass . '-' . $instanceId,
            'name' => $this->handle,
            'value' => $value,
            'field' => $this,
            'options' => $options,
            'type' => 'collections',
        ]);
    }

    /**
     * @param int $count
     * @param array $collectionsData
     * @param string $key
     * @param array $defaultOptions
     * @return array
     */
    private function _getCollections($count, $collectionsData, $key, $defaultOptions)
    {
        $options = [];
        $collections = [];

        if ($collectionsData[$key] && count($collectionsData[$key]) > 0) {
            $collections = array_merge_recursive($collections, $collectionsData[$key]);
        }

        if (count($collections) < $count && $collectionsData['link']['url']) {
            while (count($collections) < $count) {
                $nextLink = $collectionsData['link']['url'];
                $collectionsData = Shopify::getInstance()->service->getCollections(
                    '',
                    $defaultOptions,
                    $nextLink
                );
                if ($collectionsData[$key] && count($collectionsData[$key]) > 0) {
                    $collections = array_merge_recursive($collections, $collectionsData[$key]);
                }
            }
        }

        if ($collections) {
            foreach ($collections as $collection) {
                $options[] = array(
                    'label' => $this->_getCollectionType($key) . ': ' . $collection['title'],
                    'collectionId' => $collection['id'],
                );
            }
        }

        return $options;
    }

    private function _getCollectionType($key)
    {
        switch ($key) {
            case 'smart_collections':
                return Craft::t('shopify', 'Smart Collection');
                break;
            case 'custom_collections':
                return Craft::t('shopify', 'Custom Collection');
                break;
        }

        return Craft::t('shopify', 'Collection');
    }
}
