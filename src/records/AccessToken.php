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
 * Access Token record.
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 7.0.0
 *
 * @property int $id
 * @property string $accessToken
 * @property string $dateCreated
 * @property string $dateUpdated
 * @property string $uid
 */
class AccessToken extends ActiveRecord
{
    /**
     * @inheritdoc
     */
    public static function tableName(): string
    {
        return Table::ACCESS_TOKENS;
    }
}
