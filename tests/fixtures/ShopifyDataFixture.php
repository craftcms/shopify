<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\shopify\tests\fixtures;

use craft\shopify\db\Table;
use yii\test\DbFixture;

/**
 * Loads shopify_data rows directly via DB command.
 *
 * Direct inserts are used because there is no public service method for
 * saving ShopifyData records and because we must exclude generated columns
 * from the INSERT statement.
 */
class ShopifyDataFixture extends DbFixture
{
    /** @var string[] UIDs of rows inserted by this fixture, used for cleanup */
    public array $insertedUids = [];

    public function load(): void
    {
        $rows = require __DIR__ . '/data/shopify-data.php';

        foreach ($rows as $row) {
            $uid = $row['uid'];
            \Yii::$app->db->createCommand()->insert(Table::DATA, [
                'shopifyGid' => $row['shopifyGid'],
                'type' => $row['type'],
                'data' => $row['data'],
                'parentId' => $row['parentId'],
                'uid' => $uid,
                'dateCreated' => $row['dateCreated'],
                'dateUpdated' => $row['dateUpdated'],
            ])->execute();

            $this->insertedUids[] = $uid;
        }
    }

    public function unload(): void
    {
        if (!empty($this->insertedUids)) {
            \Yii::$app->db->createCommand()->delete(Table::DATA, ['uid' => $this->insertedUids])->execute();
        }
        $this->insertedUids = [];
    }
}
