<?php

namespace craft\shopify\migrations;

use craft\db\Migration;
use craft\shopify\db\Table;

/**
 * m260617_100000_rename_shopifyId_to_shopifyGid_in_data_table migration.
 */
class m260617_100000_rename_shopifyId_to_shopifyGid_in_data_table extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp(): bool
    {
        if ($this->db->columnExists(Table::DATA, 'shopifyGid')) {
            return true;
        }

        $db = $this->getDb();
        $qb = $db->getQueryBuilder();

        // Drop the existing index on shopifyId before renaming
        $this->dropIndexIfExists(Table::DATA, ['shopifyId'], false);

        // Rename shopifyId → shopifyGid
        $this->renameColumn(Table::DATA, 'shopifyId', 'shopifyGid');

        // Recreate the index on the renamed column
        $this->createIndex(null, Table::DATA, ['shopifyGid'], false);

        // Add generated shopifyId column — the numeric ID at the end of the GID
        if ($db->getIsPgsql()) {
            $expression = "regexp_replace(\"shopifyGid\", '^.*/', '')";
        } else {
            $expression = "SUBSTRING_INDEX(`shopifyGid`, '/', -1)";
        }

        $this->execute("ALTER TABLE " . Table::DATA . " ADD COLUMN " .
            $db->quoteColumnName('shopifyId') . ' ' . $qb->getColumnType($this->string()) . " GENERATED ALWAYS AS (" .
            $expression . ") STORED;");

        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown(): bool
    {
        echo "m260617_100000_rename_shopifyId_to_shopifyGid_in_data_table cannot be reverted.\n";
        return false;
    }
}
