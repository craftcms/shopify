<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\shopify\records;

use craft\db\ActiveRecord;
use craft\shopify\db\Table;

/**
 * Shopify Data record.
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 6.0.0
 *
 * @property int $id
 * @property string $shopifyId
 * @property string $type
 * @property string $data
 * @property string $parentId
 * @property string $dateCreated
 * @property string $dateUpdated
 *
 */
class ShopifyData extends ActiveRecord
{
    /**
     * @inheritdoc
     */
    public static function tableName(): string
    {
        return Table::DATA;
    }
}
