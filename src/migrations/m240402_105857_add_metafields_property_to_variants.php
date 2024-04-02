<?php

namespace craft\shopify\migrations;

use Craft;
use craft\db\Migration;
use craft\db\Query;
use craft\shopify\records\ProductData;

/**
 * m240402_105857_add_metafields_property_to_variants migration.
 */
class m240402_105857_add_metafields_property_to_variants extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp(): bool
    {
        $productRows = (new Query())
            ->select(['shopifyId', 'variants'])
            ->from('{{%shopify_productdata}}')
            ->all();

        foreach ($productRows as $product) {
            $variants = json_decode($product['variants'], true);
            foreach ($variants as &$variant) {
                if (isset($variant['metafields'])) {
                    continue;
                }

                $variant['metafields'] = [];
            }

            $this->update('{{%shopify_productdata}}', ['variants' => json_encode($variants)], ['shopifyId' => $product['shopifyId']]);
        }

        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown(): bool
    {
        echo "m240402_105857_add_metafields_property_to_variants cannot be reverted.\n";
        return false;
    }
}
