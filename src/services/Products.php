<?php

namespace craft\shopify\services;

use Craft;
use craft\base\Component;
use craft\helpers\ArrayHelper;
use craft\shopify\elements\Product as ProductElement;
use craft\shopify\events\ShopifyProductSyncEvent;
use craft\shopify\helpers\Metafields as MetafieldsHelper;
use craft\shopify\jobs\UpdateProductMetadata;
use craft\shopify\Plugin;
use craft\shopify\records\ProductData as ProductDataRecord;
use Shopify\Rest\Admin2022_10\Metafield as ShopifyMetafield;
use Shopify\Rest\Admin2022_10\Product as ShopifyProduct;

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
     * @return void
     * @throws \Throwable
     * @throws \yii\base\InvalidConfigException
     */
    public function syncAllProducts(): void
    {
        $api = Plugin::getInstance()->getApi();
        $products = $api->getAllProducts();

        foreach ($products as $product) {
            $this->createOrUpdateProduct($product);
            Craft::$app->getQueue()->push(new UpdateProductMetadata([
                'description' => Craft::t('shopify', 'Updating product metadata for “{title}”', [
                    'title' => $product->title,
                ]),
                'shopifyProductId' => $product->id,
            ]));
        }

        // Remove any products that are no longer in Shopify just in case.
        $shopifyIds = ArrayHelper::getColumn($products, 'id');
        $deletableProductElements = ProductElement::find()->shopifyId(['not', $shopifyIds])->all();

        foreach ($deletableProductElements as $element) {
            Craft::$app->elements->deleteElement($element);
        }
    }

    /**
     * @return void
     * @throws \Throwable
     * @throws \yii\base\InvalidConfigException
     */
    public function syncProductByShopifyId($id): void
    {
        $api = Plugin::getInstance()->getApi();

        $product = $api->getProductByShopifyId($id);
        $metafields = $api->getMetafieldsByProductId($id);

        $this->createOrUpdateProduct($product, $metafields);
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
            $metafields = $api->getMetafieldsByProductId($product->id);
            $this->createOrUpdateProduct($product, $metafields);
        }
    }

    /**
     * This takes the shopify data from the REST API and creates or updates a product element.
     *
     * @param ShopifyProduct $product
     * @param ShopifyMetafield[] $metafields
     * @return bool Whether the synchronization succeeded.
     */
    public function createOrUpdateProduct(ShopifyProduct $product, array $metafields = []): bool
    {
        // Expand any JSON-like properties:
        $metaFields = MetafieldsHelper::unpack($metafields);

        // Build our attribute set from the Shopify product data:
        $attributes = [
            'shopifyId' => $product->id,
            'title' => $product->title,
            'bodyHtml' => $product->body_html,
            'createdAt' => $product->created_at,
            'handle' => $product->handle,
            'images' => $product->images,
            'options' => $product->options,
            'productType' => $product->product_type,
            'publishedAt' => $product->published_at,
            'publishedScope' => $product->published_scope,
            'shopifyStatus' => $product->status,
            'tags' => $product->tags,
            'templateSuffix' => $product->template_suffix,
            'updatedAt' => $product->updated_at,
            'variants' => $product->variants,
            'vendor' => $product->vendor,
            // This one is unusual, because we’re merging two different Shopify API resources:
            'metaFields' => $metaFields,
        ];

        // Find the product data or create one
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
            /** @var ProductElement $productElement */
            $productElement = new ProductElement();
        }

        // Set attributes on the element to emulate it having been loaded with JOINed data:
        $productElement->setAttributes($attributes, false);

        $event = new ShopifyProductSyncEvent([
            'element' => $productElement,
            'source' => $product,
            'metafields' => $metafields,
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
}
