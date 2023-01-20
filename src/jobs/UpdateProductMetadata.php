<?php

namespace craft\shopify\jobs;

use Craft;
use craft\queue\BaseJob;
use craft\shopify\elements\Product;
use craft\shopify\Plugin;

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
        $product = Product::find()->shopifyId($this->shopifyProductId)->one();
        if ($product) {
            $metaFields = $api->getMetafieldsByProductId($this->shopifyProductId);
            $product->setMetafields($metaFields);
            Craft::$app->elements->saveElement($product);
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
