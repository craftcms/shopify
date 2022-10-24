<?php

namespace craft\shopify\jobs;

use Craft;
use craft\queue\BaseJob;
use craft\shopify\Plugin;

class ProductSync extends BaseJob
{
    /**
     * @inheritdoc
     */
    public function execute($queue): void
    {
        try {
            Plugin::getInstance()->getProducts()->syncAllProducts();
        } catch (\Throwable $e) {
            Craft::warning("Something went wrong syncing products: {$e->getMessage()}", __METHOD__);
        }
    }

    /**
     * @inheritdoc
     */
    protected function defaultDescription(): string
    {
        return Craft::t('app', 'Syncing all Shopify Products');
    }
}
