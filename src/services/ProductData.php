<?php

namespace craft\shopify\services;

use Craft;
use craft\base\Component;
use craft\shopify\elements\Product;
use craft\shopify\Plugin;
use craft\shopify\records\ProductData as ProductDataRecord;

/**
 * Shopify API service.
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 1.0
 *
 *
 * @property-read void $products
 */
class ProductData extends Component
{
    public function syncAllProducts()
    {
        $allData = Plugin::getInstance()->getApi()->getAllProducts();
        ProductDataRecord::deleteAll(); // May as well clean this up since we are doing a complete sync.
        foreach ($allData as $data) {
            $this->createOrUpdateProduct($data);
        }

        $shopifyIds = collect($allData)->map(function ($data) {
            return $data['id'];
        })->all();

        $deleteAbleProductElements = Product::find()->where(['not in', 'shopifyId', $shopifyIds])->all();
        foreach ($deleteAbleProductElements as $element) {
            Craft::$app->elements->deleteElement($element);
        }
    }

    /**
     * @param array $data
     * @return Product
     */
    public function createOrUpdateProduct(array $data): Product
    {
        // Transform data into the stuff we care about with the correct key names
        $data = $this->_getDataArray($data);

        // Find the product data or create one
        $productDataRecord = ProductDataRecord::find()->where(['id' => $data['id']])->one() ?: new ProductDataRecord();
        $productDataRecord->setAttributes($data, false);
        $productDataRecord->save();

        // Find the product element or create one
        $productElement = Product::find()->shopifyId($data['id'])->one();

        // We are now going to use the data to create the Product element.
        // We want to leave the element ID alone, all the other element properties lines up with the product data
        $data['shopifyId'] = $data['id'];
        unset($data['id']);

        if ($productElement) {
            $productElement->setAttributes($data);
        } else {
            $productElement = new Product($data);
        }
        Craft::$app->getElements()->saveElement($productElement, false);

        return $productElement;
    }

    /**
     * @param $data
     * @return void
     * @throws \Throwable
     * @throws \yii\db\StaleObjectException
     */
    public function deleteProductByShopifyId($id)
    {
        if ($id) {
            if ($product = Product::find()->shopifyId($id)->one()) {
                Craft::$app->getElements()->deleteElement($product);
            };
            if ($productData = ProductDataRecord::find()->where(['id' => $id])->one()) {
                $productData->delete();
            };
        }
    }

    /**
     * @param array $product
     * @return array
     */
    private function _getDataArray(array $product): array
    {
        return [
            'id' => $product['id'],
            'title' => $product['title'],
            'bodyHtml' => $product['body_html'],
            'createdAt' => $product['created_at'],
            'handle' => $product['handle'],
            'images' => $product['images'],
            'options' => $product['options'],
            'productType' => $product['product_type'],
            'publishedAt' => $product['published_at'],
            'publishedScope' => $product['published_scope'],
            'status' => $product['status'],
            'tags' => $product['tags'],
            'templateSuffix' => $product['template_suffix'],
            'updatedAt' => $product['updated_at'],
            'variants' => $product['variants'],
            'vendor' => $product['vendor'],
        ];
    }
}