<?php

namespace craft\shopify\jobs;

use craft\queue\BaseJob;
use craft\shopify\elements\Product;
use craft\shopify\helpers\Api as ApiHelper;
use craft\shopify\Plugin;
use craft\shopify\records\ProductData as ProductDataRecord;

/**
 * Updates the variants for a Shopify product.
 * @since 3.3.0
 */
class UpdateProductVariants extends BaseJob
{
    public int $shopifyProductId;

    /**
     * @inheritdoc
     */
    public function execute($queue): void
    {
        $api = Plugin::getInstance()->getApi();

        /** @var Product|null $product */
        $product = Product::find()->shopifyId($this->shopifyProductId)->one();

        if ($product) {
            $variants = $api->getVariantsByProductId($this->shopifyProductId);
            $product->setVariants($variants);
            /** @var ProductDataRecord $productData */
            $productData = ProductDataRecord::find()->where(['shopifyId' => $this->shopifyProductId])->one();
            $productData->variants = $variants;
            $productData->save();
            ApiHelper::rateLimit(); // Avoid rate limiting
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
