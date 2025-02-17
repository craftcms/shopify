<?php

namespace craft\shopify\migrations;

use craft\db\Migration;
use craft\shopify\db\Table;

/**
 * m250217_093044_add_publishedOnCurrentPublication_virtual_column migration.
 */
class m250217_093044_add_publishedOnCurrentPublication_virtual_column extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp(): bool
    {
        $db = $this->getDb();
        $qb = $db->getQueryBuilder();

        $as = $qb->jsonExtract('data', ['publishedOnCurrentPublication']);
        if (!$db->getIsPgsql()) {
            $as = 'CAST(JSON_EXTRACT(`data`, \'$."publishedOnCurrentPublication"\') as signed)';
        }

        $this->execute("ALTER TABLE " . Table::DATA . " ADD COLUMN " .
            $db->quoteColumnName('publishedOnCurrentPublication') . ' ' . $qb->getColumnType($this->boolean()) . " GENERATED ALWAYS AS (" .
            $as . ") STORED;");

        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown(): bool
    {
        echo "m250217_093044_add_publishedOnCurrentPublication_virtual_column cannot be reverted.\n";
        return false;
    }
}
