<?php

namespace craft\shopify\services;

use Craft;
use craft\base\Component;
use craft\errors\ElementNotFoundException;
use craft\events\ConfigEvent;
use craft\helpers\ArrayHelper;
use craft\helpers\Db;
use craft\helpers\ProjectConfig;
use craft\helpers\StringHelper;
use craft\models\FieldLayout;
use craft\shopify\elements\Product;
use craft\shopify\elements\Product as ProductElement;
use craft\shopify\events\ShopifyProductSyncEvent;
use craft\shopify\helpers\Metafields as MetafieldsHelper;
use craft\shopify\Plugin;
use craft\shopify\records\ProductData as ProductDataRecord;
use Shopify\Rest\Admin2023_10\Metafield as ShopifyMetafield;
use Shopify\Rest\Admin2023_10\Product as ShopifyProduct;
use Shopify\Rest\Admin2023_10\Variant as ShopifyVariant;
use Shopify\Rest\Admin2024_10\Metafield as ShopifyMetafield2410;
use Shopify\Rest\Admin2024_10\Product as ShopifyProduct2410;
use Shopify\Rest\Admin2024_10\Variant as ShopifyVariant2410;
use yii\base\Exception;
use yii\base\InvalidConfigException;

/**
 * Shopify Products service.
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 3.0
 *
 *
 * @property-read void $products
 */
class Products extends Component
{
    /**
     * @event ShopifyProductSyncEvent Event triggered just before Shopify product data is saved to a product element.
     *
     * ---
     *
     * ```php
     * use craft\shopify\events\ShopifyProductSyncEvent;
     * use craft\shopify\services\Products;
     * use yii\base\Event;
     *
     * Event::on(
     *     Products::class,
     *     Products::EVENT_BEFORE_SYNCHRONIZE_PRODUCT,
     *     function(ShopifyProductSyncEvent $event) {
     *         // Cancel the sync if a flag is set via a Shopify metafield:
     *         if ($event->metafields['do_not_sync'] ?? false) {
     *             $event->isValid = false;
     *         }
     *     }
     * );
     * ```
     */
    public const EVENT_BEFORE_SYNCHRONIZE_PRODUCT = 'beforeSynchronizeProduct';

    /**
     * @var bool Whether to slow down API requests to avoid rate limiting.
     * @since 5.2.0
     */
    public bool $throttle = false;

    /**
     * @var int The number of seconds to sleep between requests when `$throttle` is enabled.
     * @since 5.2.0
     */
    public int $sleepSeconds = 1;

    /**
     * @param ShopifyProduct|ShopifyProduct2410 $product
     * @return void
     * @throws InvalidConfigException
     * @since 4.1.0
     */
    private function _updateProduct(ShopifyProduct|ShopifyProduct2410 $product): void
    {
        $api = Plugin::getInstance()->getApi();

        $variants = $api->getVariantsByProductId($product->id);

        if ($this->throttle) {
            usleep((int) (1E6 * $this->sleepSeconds));
        }
        $productMetafields = $api->getMetafieldsByProductId($product->id);

        foreach ($variants as &$variant) {
            $variantMetafields = $api->getMetafieldsByVariantId($variant['id']);
            $variant['metafields'] = MetafieldsHelper::unpack($variantMetafields);
        }

        $this->createOrUpdateProduct($product, $productMetafields, $variants);
    }

    /**
     * @return void
     * @throws \Throwable
     * @throws \yii\base\InvalidConfigException
     * @deprecated in 6.0.0. Use [[BulkOperations::createProductsBulkOperation()]] instead.
     */
    public function syncAllProducts(): void
    {
        Craft::$app->getDeprecator()->log(__METHOD__, 'Products::syncAllProducts() has been deprecated. Use BulkOperations::createProductsBulkOperation() instead.');
        Plugin::getInstance()->getBulkOperations()->createProductsBulkOperation();
    }

    /**
     * @return void
     * @throws \Throwable
     * @throws \yii\base\InvalidConfigException
     */
    public function syncProductByShopifyId(string $id): void
    {
        Plugin::getInstance()->getBulkOperations()->createBulkOperation((string)Plugin::getInstance()->getApi()->getProductGql($id));
    }

    /**
     * @param $id
     * @return void
     * @throws \yii\base\InvalidConfigException
     */
    public function syncProductByInventoryItemId($id): void
    {
        $api = Plugin::getInstance()->getApi();

        if ($productId = $api->getProductIdByInventoryItemId($id)) {
            $product = $api->getProductByShopifyId($productId);

            $this->_updateProduct($product);
        }
    }

    /**
     * This takes the shopify data from the REST API and creates or updates a product element.
     *
     * @param ShopifyProduct|ShopifyProduct2410 $product
     * @param ShopifyMetafield[]|ShopifyMetafield2410[] $metafields
     * @param ShopifyVariant[]|ShopifyVariant2410[] $variants
     * @return bool Whether the synchronization succeeded.
     * @throws \Throwable
     * @throws ElementNotFoundException
     * @throws Exception
     * @throws \yii\db\Exception
     */
    public function createOrUpdateProduct(ShopifyProduct|ShopifyProduct2410 $product, array $metafields = [], ?array $variants = null): bool
    {
        // Expand any JSON-like properties:
        $metaFields = MetafieldsHelper::unpack($metafields);

        // Build our attribute set from the Shopify product data:
        $attributes = [
            'shopifyId' => $product->id,
            'title' => $product->title ? StringHelper::emojiToShortcodes($product->title) : null,
            'bodyHtml' => $product->body_html ? StringHelper::emojiToShortcodes($product->body_html) : null,
            'createdAt' => Db::prepareDateForDb($product->created_at),
            'handle' => $product->handle,
            'images' => $product->images,
            'options' => $product->options,
            'productType' => $product->product_type,
            'publishedAt' => Db::prepareDateForDb($product->published_at),
            'publishedScope' => $product->published_scope,
            'shopifyStatus' => $product->status,
            'tags' => $product->tags,
            'templateSuffix' => $product->template_suffix,
            'updatedAt' => Db::prepareDateForDb($product->updated_at),
            'variants' => $variants ?? $product->variants,
            'vendor' => $product->vendor,
            'metaFields' => $metaFields,
        ];

        // Find the product data or create one
        /** @var ProductDataRecord $productDataRecord */
        $productDataRecord = ProductDataRecord::find()->where(['shopifyId' => $product->id])->one() ?: new ProductDataRecord();

        // Set attributes and save:
        $productDataRecord->setAttributes($attributes, false);
        $productDataRecord->save();

        // Find the product element or create one
        /** @var ProductElement|null $productElement */
        $productElement = ProductElement::find()
            ->shopifyId($product->id)
            ->status(null)
            ->one();

        if ($productElement === null) {
            $productElement = new ProductElement();
        }

        // Set attributes on the element to emulate it having been loaded with JOINed data:
        $productElement->setAttributes($attributes, false);

        $event = new ShopifyProductSyncEvent([
            'element' => $productElement,
            'source' => $product,
        ]);
        $this->trigger(self::EVENT_BEFORE_SYNCHRONIZE_PRODUCT, $event);

        if (!$event->isValid) {
            Craft::warning("Synchronization of Shopify product ID #{$product->id} was stopped by a plugin.", 'shopify');

            return false;
        }

        if (!Craft::$app->getElements()->saveElement($productElement)) {
            Craft::error("Failed to synchronize Shopify product ID #{$product->id}.", 'shopify');

            return false;
        }

        return true;
    }

    /**
     * Deletes a product element by the Shopify ID.
     *
     * @param $id
     * @return void
     */
    public function deleteProductByShopifyId($id): void
    {
        if ($id) {
            if ($product = ProductElement::find()->shopifyId($id)->one()) {
                // We hard delete because it will have been hard deleted in Shopify
                Craft::$app->getElements()->deleteElement($product, true);
            }
            if ($productData = ProductDataRecord::find()->where(['shopifyId' => $id])->one()) {
                $productData->delete();
            }
        }
    }

    /**
     * Gets a Product element ID from a shopify ID.
     *
     * @param $id
     * @return int
     */
    public function getProductIdByShopifyId($id): int
    {
        return ProductElement::find()->shopifyId($id)->one()->id;
    }

    /**
     * Handle field layout change
     *
     * @throws \Throwable
     */
    public function handleChangedFieldLayout(ConfigEvent $event): void
    {
        $data = $event->newValue;

        ProjectConfig::ensureAllFieldsProcessed();
        $fieldsService = Craft::$app->getFields();

        if (empty($data) || empty(reset($data))) {
            // Delete the field layout
            $fieldsService->deleteLayoutsByType(Product::class);
            return;
        }

        // Save the field layout
        $layout = FieldLayout::createFromConfig(reset($data));
        $layout->id = $fieldsService->getLayoutByType(Product::class)->id;
        $layout->type = Product::class;
        $layout->uid = key($data);
        $fieldsService->saveLayout($layout, false);


        // Invalidate product caches
        Craft::$app->getElements()->invalidateCachesForElementType(Product::class);
    }

    /**
     * Handle field layout being deleted
     */
    public function handleDeletedFieldLayout(): void
    {
        Craft::$app->getFields()->deleteLayoutsByType(Product::class);
    }
}
