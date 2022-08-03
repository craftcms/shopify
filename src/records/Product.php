<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\shopify\records;

use craft\shopify\db\Table;
use craft\db\ActiveRecord;
use craft\records\Element;
use DateTime;
use yii\db\ActiveQueryInterface;

/**
 * Product record.
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 1.0
 *
 * @property int $id
 * @property int $shopifyId
 *
 */
class Product extends ActiveRecord
{
    /**
     * @inheritdoc
     */
    public static function tableName(): string
    {
        return Table::PRODUCTS;
    }

    public function getElement(): ActiveQueryInterface
    {
        return $this->hasOne(Element::class, ['id' => 'id']);
    }

    public function getData(): ActiveQueryInterface
    {
        return $this->hasOne(ProductData::class, ['shopifyId' => 'id']);
    }
}
