<?php

namespace craft\shopify\services;

use Craft;
use craft\base\Component;
use craft\errors\ElementNotFoundException;
use craft\events\ConfigEvent;
use craft\helpers\ArrayHelper;
use craft\helpers\Db;
use craft\helpers\Json;
use craft\helpers\ProjectConfig;
use craft\helpers\StringHelper;
use craft\models\FieldLayout;
use craft\shopify\collections\VariantCollection;
use craft\shopify\db\Table;
use craft\shopify\elements\Product;
use craft\shopify\events\ShopifyProductSyncEvent;
use craft\shopify\models\Variant;
use craft\shopify\Plugin;
use craft\shopify\records\ShopifyData;
use GraphQL\QueryBuilder\QueryBuilder;
use yii\base\Exception;
use yii\base\InvalidConfigException;
use yii\db\StaleObjectException;

/**
 * Shopify Products service.
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 3.0
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
     * @deprecated in 6.0.0. Use [[BulkOperations::createProductsBulkOperation()]] instead.
     */
    public function syncAllProducts(): void
    {
        Craft::$app->getDeprecator()->log(__METHOD__, 'Products::syncAllProducts() has been deprecated. Use BulkOperations::createProductsBulkOperation() instead.');
        Plugin::getInstance()->getBulkOperations()->createProductsBulkOperation();
    }

    /**
     * @param string $id
     * @return void
     * @throws InvalidConfigException
     * @throws \yii\db\Exception
     */
    public function syncProductByShopifyId(string $id): void
    {
        $shopifyId = $this->normalizeShopifyGid($id);
        Plugin::getInstance()->getBulkOperations()->createBulkOperation((string)Plugin::getInstance()->getApi()->getProductGql($id), $shopifyId);
    }

    /**
     * @param $id
     * @return void
     * @throws \yii\base\InvalidConfigException
     */
    public function syncProductByInventoryItemId($id): void
    {
        // Make sure the ID has the gql prefix
        $id = $this->normalizeShopifyGid($id, 'InventoryItem');

        $query = Plugin::getInstance()->getApi()->createQuery('inventoryItem', [
            'id',
            'variant' => [
                'id',
                'product' => [
                    'id',
                ],
            ],
        ], function(QueryBuilder $builder) use ($id) {
            $builder->setArgument('id', $id);
        });

        $response = Plugin::getInstance()->getApi()->query($query);

        if (empty($response) || empty($response['data']['inventoryItem']['variant']['product']['id'])) {
            return;
        }

        $productId = $response['data']['inventoryItem']['variant']['product']['id'];

        $this->syncProductByShopifyId($productId);
    }

    /**
     * This takes the shopify data from the REST API and creates or updates a product element.
     *
     * @param array $product
     * @return bool Whether the synchronization succeeded.
     * @throws ElementNotFoundException
     * @throws Exception
     * @throws \Throwable
     */
    public function createOrUpdateProduct(array $product): bool
    {
        // Build our attribute set from the Shopify product data:
        $attributes = [
            'shopifyId' => str_replace('gid://shopify/Product/', '', $product['id']),
            'shopifyGid' => $product['id'],
            'title' => $product['title'] ? StringHelper::emojiToShortcodes($product['title']) : null,
            'descriptionHtml' => $product['descriptionHtml'] ? StringHelper::emojiToShortcodes($product['descriptionHtml']) : null,
            'createdAt' => Db::prepareDateForDb($product['createdAt']),
            'handle' => $product['handle'],
            'options' => $product['options'],
            'productType' => $product['productType'],
            'publishedAt' => Db::prepareDateForDb($product['publishedAt']),
            'publishedOnCurrentPublication' => (bool)$product['publishedOnCurrentPublication'],
            'shopifyStatus' => $product['status'],
            'tags' => $product['tags'],
            'templateSuffix' => $product['templateSuffix'],
            'updatedAt' => Db::prepareDateForDb($product['updatedAt']),
            'vendor' => $product['vendor'],
        ];

        // Find the product element or create one
        /** @var Product|null $productElement */
        $productElement = Product::find()
            ->shopifyGid($product['id'])
            ->status(null)
            ->one();

        if ($productElement === null) {
            $productElement = new Product();
        }

        // Set attributes on the element to emulate it having been loaded with JOINed data:
        $productElement->setAttributes($attributes, false);

        $event = new ShopifyProductSyncEvent([
            'element' => $productElement,
            'source' => $product,
        ]);
        $this->trigger(self::EVENT_BEFORE_SYNCHRONIZE_PRODUCT, $event);

        if (!$event->isValid) {
            Craft::warning("Synchronization of Shopify product ID #{$product['id']} was stopped by a plugin.", 'shopify');

            return false;
        }

        if (!Craft::$app->getElements()->saveElement($productElement)) {
            Craft::error("Failed to synchronize Shopify product ID #{$product['id']}.", 'shopify');

            return false;
        }

        return true;
    }

    /**
     * @param string $shopifyId
     * @return string
     * @since 6.0.0
     */
    public function normalizeShopifyGid(string $shopifyId, string $type = 'Product'): string
    {
        return str_starts_with($shopifyId, 'gid://shopify/' . $type . '/') ? $shopifyId : 'gid://shopify/' . $type . '/' . $shopifyId;
    }

    /**
     * Deletes a product element by the Shopify ID.
     *
     * @param $id
     * @return void
     * @throws \Throwable
     * @throws StaleObjectException
     */
    public function deleteProductByShopifyId($id): void
    {
        if ($id) {
            if ($product = Product::find()->shopifyId($id)->one()) {
                // We hard delete because it will have been hard deleted in Shopify
                Craft::$app->getElements()->deleteElement($product, true);
            }

            // Delete data in shopify data table
            // Delete the product data
            $shopifyId = $this->normalizeShopifyGid($id);
            $this->deleteShopifyDataByShopifyId($shopifyId);
        }
    }

    /**
     * @param string $shopifyId
     * @return void
     * @throws StaleObjectException
     * @throws \Throwable
     * @since 6.0.0
     */
    public function deleteShopifyDataByShopifyId(string $shopifyId): void
    {
        // Support both id and gid
        $shopifyId = $this->normalizeShopifyGid($shopifyId);

        /** @var ShopifyData|null $shopifyData */
        $shopifyData = ShopifyData::find()->where(['shopifyId' => $shopifyId])->one();

        // Delete if possible
        $shopifyData?->delete();

        // Delete any child data that may still exist
        /** @var ShopifyData[] $shopifyData */
        $shopifyData = ShopifyData::find()->where(['parentId' => $shopifyId])->all();
        $childIds = [];
        foreach ($shopifyData as $data) {
            $childIds[] = $data->shopifyId;
            $data->delete();
        }

        // Loop through any child data and remove that too
        while (!empty($childIds)) {
            $childId = array_shift($childIds);
            /** @var ShopifyData[] $shopifyData */
            $shopifyData = ShopifyData::find()->where(['parentId' => $childId])->all();
            foreach ($shopifyData as $data) {
                $childIds[] = $data->shopifyId;
                $data->delete();
            }
        }
    }

    /**
     * @param array|Product[] $products
     * @return array
     * @since 6.0.0
     */
    public function eagerLoadMetafieldsForProducts(array $products): array
    {
        return $this->_eagerLoadTypeOnProducts($products, 'Metafield', function($product, $rows) {
            $metafields = collect($rows)
                ->mapWithKeys(function($d, $key) {
                    /** @var ShopifyData $d */

                    // Map if the data has `key` and `value` properties
                    if (!isset($d->data['key']) || !isset($d->data['value'])) {
                        return [];
                    }

                    return [
                        $d->data['key'] => Json::decodeIfJson($d->data['value']),
                    ];
                })
                ->all();

            $product->setMetafields($metafields);
        });
    }

    /**
     * @param array|Product[] $products
     * @return array
     * @since 6.0.0
     */
    public function eagerLoadImagesForProducts(array $products): array
    {
        return $this->_eagerLoadTypeOnProducts($products, 'MediaImage', function($product, $rows) {
            $product->setImages(array_column($rows, 'data'));
        });
    }

    /**
     * @param array|Product[] $products
     * @return array
     * @since 6.0.0
     */
    public function eagerLoadVariantsForProducts(array $products): array
    {
        $variantIds = [];
        $variantsByProductId = [];
        $return = $this->_eagerLoadTypeOnProducts($products, 'ProductVariant', function($product, $rows) use (&$variantsByProductId, &$variantIds) {
            foreach ($rows as $row) {
                $variantIds[] = $row->shopifyId;
            }

            $variantsByProductId[$product->shopifyGid] = $rows;
        });

        // If we are eager loading the variants, for best performance we should also eager load the metafields on the variants
        $metafieldsData = collect();
        if (!empty($variantIds)) {
            $metafieldsData = Plugin::getInstance()
                ->getApi()
                ->getShopifyDataByType('Metafield', $variantIds, true)
                ->groupBy('parentId');
        }

        foreach ($return as $product) {
            $variants = VariantCollection::make($variantsByProductId[$product->shopifyGid]);

            if ($metafieldsData->isNotEmpty()) {
                $variants->map(function(Variant$variant) use ($metafieldsData) {
                    $metafields = $metafieldsData->get($variant->shopifyId);
                    if (!empty($metafields)) {
                        $variant->setMetafields(collect($metafields)->mapWithKeys(function($d) {
                            return [
                                $d->data['key'] => Json::decodeIfJson($d->data['value']),
                            ];
                        })->all());
                    }
                });
            }

            $product->setMetafields($variants);
        }

        return $return;
    }

    /**
     * @param array|Product[] $products
     * @param string $type
     * @param callable(Product, ShopifyData[]): void $callback
     * @return array
     * @throws InvalidConfigException
     */
    private function _eagerLoadTypeOnProducts(array $products, string $type, callable $callback): array
    {
        $productIds = ArrayHelper::getColumn($products, 'shopifyGid');

        $data = Plugin::getInstance()->getApi()->getShopifyDataByType($type, $productIds, true);

        // Group objects by their owner:
        $data = $data->groupBy('parentId');

        // Give each product a chance to handle the rows, directly:
        foreach ($products as $product) {
            $productData = $data->get($product->shopifyGid);
            $callback($product, empty($productData) ? [] : $productData->all());
        }

        return $products;
    }


    /**
     * Gets a Product element ID from a shopify ID.
     *
     * @param $id
     * @return int
     */
    public function getProductIdByShopifyId($id): int
    {
        return Product::find()->shopifyId($id)->one()->id;
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
