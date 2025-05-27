<?php

namespace craft\shopify\migrations;

use craft\db\Migration;
use craft\shopify\db\Table;

/**
 * m250527_133823_fix_maria_db_bool_generated_columns migration.
 */
class m250527_133823_fix_maria_db_bool_generated_columns extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp(): bool
    {
        // Skip migration if not using MariaDB
        $db = $this->getDb();
        if (!$db->getIsMaria()) {
            return true;
        }

        $col = 'publishedOnCurrentPublication';
        $this->dropColumn(Table::DATA, $col);
        $qb = $db->getQueryBuilder();

        $as = 'CAST(IF(JSON_EXTRACT(`data`, \'$."' . $col . '"\'), 1, 0) as signed)';

        $this->execute("ALTER TABLE " . Table::DATA . " ADD COLUMN " .
            $db->quoteColumnName($col) . ' ' . $qb->getColumnType($this->boolean()) . " GENERATED ALWAYS AS (" .
            $as . ") STORED;");

        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown(): bool
    {
        echo "m250527_133823_fix_maria_db_bool_generated_columns cannot be reverted.\n";
        return false;
    }
}
