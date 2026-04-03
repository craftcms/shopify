<?php

namespace craft\shopify\migrations;

use craft\db\Migration;
use craft\shopify\db\Table;

/**
 * m260401_070605_add_totalInventory_generated_column migration.
 */
class m260401_070605_add_totalInventory_generated_column extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp(): bool
    {
        $col = 'totalInventory';
        if ($this->db->columnExists(Table::DATA, $col)) {
            return true;
        }

        $db = $this->getDb();
        $qb = $db->getQueryBuilder();

        $expression = $qb->jsonExtract('data', [$col]);

        if ($db->getIsPgsql()) {
            $expression = "($expression)::int";
        } else {
            $expression = "CAST(JSON_UNQUOTE($expression) AS SIGNED)";
        }

        $this->execute("ALTER TABLE " . Table::DATA . " ADD COLUMN " .
            $db->quoteColumnName($col) . ' ' . $qb->getColumnType($this->integer()) . " GENERATED ALWAYS AS (" .
            $expression . ") STORED;");

        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown(): bool
    {
        echo "m260401_070605_add_totalInventory_generated_column cannot be reverted.\n";
        return false;
    }
}
