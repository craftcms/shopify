<?php

declare(strict_types=1);

namespace CraftCms\Shopify\Product;

use craft\shopify\collections\VariantCollection;
use craft\shopify\elements\Product;
use craft\shopify\models\Variant;
use craft\shopify\records\ShopifyData;
use CraftCms\Cms\Element\ElementCaches;
use CraftCms\Cms\Element\Elements;
use CraftCms\Cms\Field\Fields;
use CraftCms\Cms\FieldLayout\FieldLayout;
use CraftCms\Cms\ProjectConfig\Events\ConfigEvent;
use CraftCms\Cms\ProjectConfig\ProjectConfigHelper;
use CraftCms\Cms\Support\Query;
use CraftCms\Cms\Support\Str;
use CraftCms\Shopify\Api\Api;
use CraftCms\Shopify\BulkOperation\BulkOperations;
use CraftCms\Shopify\Product\Events\ProductSyncing;
use GraphQL\QueryBuilder\QueryBuilder;
use Illuminate\Container\Attributes\Singleton;
use Illuminate\Support\Facades\Log;

/**
 * Shopify Products service.
 */
#[Singleton]
class Products
{
    public function __construct(
        private readonly Api $api,
        private readonly BulkOperations $bulkOperations,
        private readonly Elements $elements,
        private readonly ElementCaches $elementCaches,
        private readonly Fields $fields,
    ) {
    }

    /**
     * @param string $gid
     * @return void
     * @since 8.0.0
     */
    public function syncProductByShopifyGid(string $gid): void
    {
        $gid = $this->normalizeShopifyGid($gid);
        $this->bulkOperations->createBulkOperation((string)$this->api->getProductGql($gid), $gid);
    }

    /**
     * @param string $id
     * @return void
     * @deprecated in 8.0.0. Use [[syncProductByShopifyGid()]] instead.
     */
    public function syncProductByShopifyId(string $id): void
    {
        $this->syncProductByShopifyGid($id);
    }

    /**
     * @param $id
     * @return void
     */
    public function syncProductByInventoryItemId($id): void
    {
        // Make sure the ID has the gql prefix
        $id = $this->normalizeShopifyGid((string)$id, 'InventoryItem');

        $query = $this->api->createQuery('inventoryItem', [
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

        $item = $this->api->query($query);

        if (!$item) {
            return;
        }

        $productId = $item['variant']['product']['id'];

        $this->syncProductByShopifyGid($productId);
    }

    /**
     * This takes the shopify data from the REST API and creates or updates a product element.
     *
     * @param array $product
     * @return bool Whether the synchronization succeeded.
     * @throws \Throwable
     */
    public function createOrUpdateProduct(array $product): bool
    {
        // Build our attribute set from the Shopify product data:
        $attributes = [
            'shopifyId' => str_replace('gid://shopify/Product/', '', $product['id']),
            'shopifyGid' => $product['id'],
            'title' => $product['title'] ? Str::emojiToShortcodes($product['title']) : null,
            'descriptionHtml' => $product['descriptionHtml'] ? Str::emojiToShortcodes($product['descriptionHtml']) : null,
            'createdAt' => Query::prepareDateForDb($product['createdAt']),
            'handle' => $product['handle'],
            'options' => $product['options'],
            'productType' => $product['productType'],
            'publishedAt' => Query::prepareDateForDb($product['publishedAt']),
            'shopifyStatus' => $product['status'],
            'tags' => $product['tags'] ?? [],
            'templateSuffix' => $product['templateSuffix'],
            'updatedAt' => Query::prepareDateForDb($product['updatedAt']),
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
        $productElement->setAttributes($attributes);

        event($event = new ProductSyncing($productElement, $product));

        if (!$event->isValid) {
            Log::warning("Synchronization of Shopify product ID #{$product['id']} was stopped by a plugin.");

            return false;
        }

        if (!$this->elements->saveElement($productElement)) {
            Log::error("Failed to synchronize Shopify product ID #{$product['id']}.");

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
     * Deletes a product element by the Shopify GID.
     *
     * @param string $gid
     * @return bool Whether the deletion was performed. Returns false if `$gid` is empty.
     * @throws \Throwable
     * @since 8.0.0
     */
    public function deleteProductByShopifyGid(string $gid): bool
    {
        if (!$gid) {
            return false;
        }

        $gid = $this->normalizeShopifyGid($gid);

        if ($product = Product::find()->shopifyGid($gid)->one()) {
            // We hard delete because it will have been hard deleted in Shopify
            $this->elements->deleteElement($product, true);
        }

        $this->deleteShopifyDataByShopifyGid($gid);

        if (!$this->bulkOperations->deleteQueuedBulkOperationsByShopifyGid($gid)) {
            Log::warning("Could not remove queued bulk operations for deleted product $gid because the queue was busy.");
        }

        return true;
    }

    /**
     * @param $id
     * @return void
     * @throws \Throwable
     * @deprecated in 8.0.0. Use [[deleteProductByShopifyGid()]] instead.
     */
    public function deleteProductByShopifyId($id): void
    {
        if (!$id) {
            return;
        }

        $this->deleteProductByShopifyGid((string)$id);
    }

    /**
     * @param string $gid
     * @return void
     * @throws \Throwable
     * @since 8.0.0
     */
    public function deleteShopifyDataByShopifyGid(string $gid): void
    {
        // Support both numeric ID and GID
        $gid = $this->normalizeShopifyGid($gid);

        /** @var ShopifyData|null $shopifyData */
        $shopifyData = ShopifyData::find()->where(['shopifyGid' => $gid])->one();

        // Delete if possible
        $shopifyData?->delete();

        // Delete any child data that may still exist
        /** @var ShopifyData[] $shopifyData */
        $shopifyData = ShopifyData::find()->where(['parentId' => $gid])->all();
        $childIds = [];
        foreach ($shopifyData as $data) {
            $childIds[] = $data->shopifyGid;
            $data->delete();
        }

        // Loop through any child data and remove that too
        while (!empty($childIds)) {
            $childId = array_shift($childIds);
            /** @var ShopifyData[] $shopifyData */
            $shopifyData = ShopifyData::find()->where(['parentId' => $childId])->all();
            foreach ($shopifyData as $data) {
                $childIds[] = $data->shopifyGid;
                $data->delete();
            }
        }
    }

    /**
     * @param string $shopifyId
     * @return void
     * @throws \Throwable
     * @deprecated in 8.0.0. Use [[deleteShopifyDataByShopifyGid()]] instead.
     */
    public function deleteShopifyDataByShopifyId(string $shopifyId): void
    {
        $this->deleteShopifyDataByShopifyGid($shopifyId);
    }

    /**
     * @param array|Product[] $products
     * @return array
     * @since 6.0.0
     */
    public function eagerLoadMetafieldsForProducts(array $products): array
    {
        return $this->eagerLoadTypeOnProducts($products, 'Metafield', function($product, $rows) {
            $product->setMetafields($rows);
        });
    }

    /**
     * @param array|Product[] $products
     * @return array
     * @since 6.0.0
     */
    public function eagerLoadImagesForProducts(array $products): array
    {
        return $this->eagerLoadTypeOnProducts($products, 'MediaImage', function($product, $rows) {
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
        $return = $this->eagerLoadTypeOnProducts($products, 'ProductVariant', function($product, $rows) use (&$variantsByProductId, &$variantIds) {
            foreach ($rows as $row) {
                $variantIds[] = $row->shopifyGid;
            }

            $variantsByProductId[$product->shopifyGid] = $rows;
        });

        // If we are eager loading the variants, for best performance we should also eager load the metafields on the variants
        $metafieldsData = collect();
        if (!empty($variantIds)) {
            $metafieldsData = $this->api
                ->getShopifyDataByType('Metafield', $variantIds, true)
                ->groupBy('parentId');
        }

        foreach ($return as $product) {
            $variants = VariantCollection::make($variantsByProductId[$product->shopifyGid]);

            if ($metafieldsData->isNotEmpty()) {
                $variants->map(function(Variant $variant) use ($metafieldsData) {
                    $metafields = $metafieldsData->get($variant->shopifyGid);
                    if (!empty($metafields)) {
                        $variant->setMetafields($metafields->all());
                    }
                });
            }

            $product->setVariants($variants);
        }

        return $return;
    }

    /**
     * @param array|Product[] $products
     * @param string $type
     * @param callable(Product, ShopifyData[]): void $callback
     * @return array
     */
    private function eagerLoadTypeOnProducts(array $products, string $type, callable $callback): array
    {
        $productIds = array_map(fn(Product $product) => $product->shopifyGid, $products);

        $data = $this->api->getShopifyDataByType($type, $productIds, true);

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

        ProjectConfigHelper::ensureAllFieldsProcessed();

        if (empty($data) || empty(reset($data))) {
            // Delete the field layout
            $this->fields->deleteLayoutsByType(Product::class);
            return;
        }

        // Save the field layout
        $layout = FieldLayout::createFromConfig(reset($data));
        $layout->id = $this->fields->getLayoutByType(Product::class)->id;
        $layout->type = Product::class;
        $layout->uid = key($data);
        $this->fields->saveLayout($layout, false);

        // Invalidate product caches
        $this->elementCaches->invalidateForElementType(Product::class);
    }

    /**
     * Handle field layout being deleted
     */
    public function handleDeletedFieldLayout(): void
    {
        $this->fields->deleteLayoutsByType(Product::class);
    }
}
