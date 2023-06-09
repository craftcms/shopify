<?php

namespace craft\shopify\jobs;

use Craft;
use craft\queue\BaseJob;
use craft\shopify\elements\Product;
use craft\shopify\helpers\Metafields as MetafieldsHelper;
use craft\shopify\Plugin;
use craft\shopify\records\ProductData as ProductDataRecord;

/**
 * Updates the metadata for a Shopify product.
 */
class UpdateProductMetadata extends BaseJob
{
    public int $shopifyProductId;

    /**
     * @inheritdoc
     */
    public function execute($queue): void
    {
        $api = Plugin::getInstance()->getApi();

        if ($product = Product::find()->shopifyId($this->shopifyProductId)->one()) {
            $metaFieldsObjects = $api->getMetafieldsByProductId($this->shopifyProductId);
            $metaFields = MetafieldsHelper::unpack($metaFieldsObjects);
            $product->setMetafields($metaFields);
            $productData = ProductDataRecord::find()->where(['shopifyId' => $this->shopifyProductId])->one();
            $productData->metaFields = $metaFields;
            $productData->save();
            sleep(1); // Avoid rate limiting
        }
    }

    /**
     * @inheritdoc
     */
    protected function defaultDescription(): ?string
    {
        return null;
    }
}
