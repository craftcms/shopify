<?php

namespace craft\shopify\services;

use Craft;
use craft\base\Component;
use craft\shopify\elements\Product;
use craft\shopify\Plugin;
use craft\shopify\records\ProductData as ProductDataRecord;

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
     * @return void
     * @throws \Throwable
     * @throws \yii\base\InvalidConfigException
     */
    public function syncAllProducts(): void
    {
        $allData = Plugin::getInstance()->getApi()->getAllProducts();

        foreach ($allData as $data) {
            $this->createOrUpdateProduct($data);
        }

        // Remove any products that are no longer in Shopify just in case.
        $shopifyIds = collect($allData)->pluck('id')->all();
        $deleteAbleProductElements = Product::find()->shopifyId(['not', $shopifyIds])->all();
        foreach ($deleteAbleProductElements as $element) {
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
        $data = Plugin::getInstance()->getApi()->getProductByShopifyId($id);
        $this->createOrUpdateProduct($data);
    }

    /**
     * This takes the shopify data from the REST API and creates or updates a product element.
     *
     * @param array $shopifyProductData
     * @return Product
     */
    public function createOrUpdateProduct(array $shopifyProductData): Product
    {
        // Transform data into the stuff we care about with the correct key names
        $shopifyProductData = $this->_getDataArray($shopifyProductData);

        // Find the product data or create one
        $productDataRecord = ProductDataRecord::find()->where(['shopifyId' => $shopifyProductData['shopifyId']])->one() ?: new ProductDataRecord();
        $productDataRecord->setAttributes($shopifyProductData, false);
        $productDataRecord->save();

        // Find the product element or create one
        /** @var Product|null $productElement */
        $productElement = Product::find()->shopifyId($shopifyProductData['shopifyId'])->status(null)->one();

        if ($productElement === null) {
            /** @var Product $productElement */
            $productElement = new Product();
        }

        $productElement->setAttributes($shopifyProductData, false);

        Craft::$app->getElements()->saveElement($productElement);

        return $productElement;
    }

    /**
     * Deletes a product element by the shopify ID.
     *
     * @param $id
     * @return void
     */
    public function deleteProductByShopifyId($id): void
    {
        if ($id) {
            if ($product = Product::find()->shopifyId($id)->one()) {
                // We hard delete because it will have been hard deleted in Shopify
                Craft::$app->getElements()->deleteElement($product, true);
            }
            if ($productData = ProductDataRecord::find()->where(['id' => $id])->one()) {
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
        return Product::find()->shopifyId($id)->one()->id;
    }

    /**
     * @param array $product
     * @return array
     */
    private function _getDataArray(array $product): array
    {
        return [
            'shopifyId' => $product['id'],
            'title' => $product['title'],
            'bodyHtml' => $product['body_html'],
            'createdAt' => $product['created_at'],
            'handle' => $product['handle'],
            'images' => $product['images'],
            'options' => $product['options'],
            'productType' => $product['product_type'],
            'publishedAt' => $product['published_at'],
            'publishedScope' => $product['published_scope'],
            'shopifyStatus' => $product['status'],
            'tags' => $product['tags'],
            'templateSuffix' => $product['template_suffix'],
            'updatedAt' => $product['updated_at'],
            'variants' => $product['variants'],
            'vendor' => $product['vendor'],
            'metaFields' => $product['metaFields'],
        ];
    }
}
