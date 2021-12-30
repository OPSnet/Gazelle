<?php

namespace Gazelle;

class DB extends Base {

    /**
     * Skip foreign key checks
     * @param bool $relax true if foreign key checks should be skipped
     */
    public function relaxConstraints(bool $relax) {
        if ($relax) {
            self::$db->prepared_query("SET foreign_key_checks = 0");
        } else {
            self::$db->prepared_query("SET foreign_key_checks = 1");
        }
        return $this;
    }

    public function globalStatus(): array {
        self::$db->prepared_query('SHOW GLOBAL STATUS');
        return self::$db->to_array('Variable_name', MYSQLI_ASSOC, false);
    }

    public function globalVariables(): array {
        self::$db->prepared_query('SHOW GLOBAL VARIABLES');
        return self::$db->to_array('Variable_name', MYSQLI_ASSOC, false);
    }

    public function selectQuery(string $tableName): string {
        self::$db->prepared_query("
            SELECT concat(column_name, ' /* ', data_type, ' */') AS c
            FROM information_schema.columns
            WHERE table_schema = ?
                AND table_name = ?
            ORDER BY ordinal_position
            ", SQLDB, $tableName
        );
        return "SELECT " . implode(",\n    ", self::$db->collect(0)) . "\nFROM $tableName\nWHERE --";
    }

    /**
     * Soft delete a row from a table <t> by inserting it into deleted_<t> and then delete from <t>
     * @param string $schema the schema name
     * @param string $table the table name
     * @param array $condition Must be an array of arrays, e.g. [[column_name, column_value]] or [[col1, val1], [col2, val2]]
     *                         Will be used to identify the row (or rows) to delete
     * @param boolean $delete whether to delete the matched rows
     * @return array 2 elements, true/false and message if false
     */
    public function softDelete($schema, $table, array $condition, $delete = true) {
        $sql = 'SELECT column_name, column_type FROM information_schema.columns WHERE table_schema = ? AND table_name = ? ORDER BY 1';
        self::$db->prepared_query($sql, $schema, $table);
        $t1 = self::$db->to_array();
        $n1 = count($t1);

        $softDeleteTable = 'deleted_' . $table;
        self::$db->prepared_query($sql, $schema, $softDeleteTable);
        $t2 = self::$db->to_array();
        $n2 = count($t2);

        if (!$n1) {
            return [false, "No such table $table"];
        }
        elseif (!$n2) {
            return [false, "No such table $softDeleteTable"];
        }
        elseif ($n1 != $n2) {
            // tables do not have the same number of columns
            return [false, "$table and $softDeleteTable column count mismatch ($n1 != $n2)"];
        }

        $column = [];
        for ($i = 0; $i < $n1; ++$i) {
            // a column does not have the same name or datatype
            if (strtolower($t1[$i][0]) != strtolower($t2[$i][0]) || $t1[$i][1] != $t2[$i][1]) {
                return [false, "{$table}: column {$t1[$i][0]} name or datatype mismatch {$t1[$i][0]}:{$t2[$i][0]} {$t1[$i][1]}:{$t2[$i][1]}"];
            }
            $column[] = $t1[$i][0];
        }
        $columnList = implode(', ', $column);
        $conditionList = implode(' AND ', array_map(fn($c) => "{$c[0]} = ?", $condition));
        $argList = array_map(fn($c) => $c[1], $condition);

        $sql = "INSERT INTO $softDeleteTable
                  ($columnList)
            SELECT $columnList
            FROM $table
            WHERE $conditionList";
        try {
            self::$db->prepared_query($sql, ...$argList);
            if (self::$db->affected_rows() == 0) {
                return [false, "condition selected 0 rows"];
            }
        } catch (\DB_MYSQL_DuplicateKeyException $e) {
            // do nothing, for some reason it was already deleted
        }

        if (!$delete) {
            return [true, "rows affected: " . self::$db->affected_rows()];
        }

        $sql = "DELETE FROM $table WHERE $conditionList";
        self::$db->prepared_query($sql, ...$argList);
        return [true, "rows deleted: " . self::$db->affected_rows()];
    }

    /**
     * Calculate page and SQL limit
     * @param int $pageSize records per page
     * @param int $page current page or a falsey value to fetch from $_REQUEST
     */
    public static function pageLimit(int $pageSize, int $page = 0) {
        if (!$page) {
            $page = max(1, (int)($_REQUEST['page'] ?? 0));
        }

        return [$page, $pageSize, $pageSize * ($page - 1)];
    }

    /**
     * How many queries have been runnning for more than 20 minutes?
     */
    public function longRunning(): int {
        return self::$db->scalar("
            SELECT count(*)
            FROM information_schema.processlist
            WHERE COMMAND NOT IN ('Sleep')
                AND TIME > 1200;
        ");
    }
}
