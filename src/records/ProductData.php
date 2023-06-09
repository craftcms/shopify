<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\shopify\records;

use craft\db\ActiveRecord;
use craft\shopify\db\Table;
use yii\db\ActiveQueryInterface;

/**
 * Product Data record.
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 3.0
 *
 * @property int $shopifyId
 * @property string $title
 * @property string $bodyHtml
 * @property string $createdAt
 * @property string $handle
 * @property array $images
 * @property string $options
 * @property string $productType
 * @property string $publishedAt
 * @property string $publishedScope
 * @property string $shopifyStatus
 * @property string $tags
 * @property string $templateSuffix
 * @property string $updatedAt
 * @property array $variants
 * @property string $vendor
 * @property array $metaFields
 * @property string $dateUpdated
 *
 */
class ProductData extends ActiveRecord
{
    /**
     * @inheritdoc
     */
    public static function tableName(): string
    {
        return Table::PRODUCTDATA;
    }

    public function getData(): ActiveQueryInterface
    {
        return $this->hasOne(Product::class, ['id' => 'shopifyId']);
    }
}
