<?php

declare(strict_types=1);

namespace Gazelle;

use Gazelle\Enum\Direction;

class DB extends Base {
    public static function DB(bool $readWrite = true): DB\Mysql {
        if ($readWrite) {
            return self::$db ??= new DB\Mysql(
                MYSQL_DB,
                MYSQL_RW_USER,
                MYSQL_RW_PASS,
                MYSQL_HOST,
                MYSQL_PORT,
                MYSQL_SOCK,
            );
        }
        // R/O connections get a fresh instance each invocation
        return new DB\Mysql(
            MYSQL_DB,
            MYSQL_RO_USER,
            MYSQL_RO_PASS,
            MYSQL_HOST,
            MYSQL_PORT,
            MYSQL_SOCK,
        );
    }

    /**
     * Skip foreign key checks
     * @param bool $relax true if foreign key checks should be skipped
     */
    public function relaxConstraints(bool $relax): static {
        if ($relax) {
            self::$db->prepared_query("SET foreign_key_checks = 0");
        } else {
            self::$db->prepared_query("SET foreign_key_checks = 1");
        }
        return $this;
    }

    public function now(): string {
        return (string)self::$db->scalar("SELECT now()");
    }

    public function globalStatus(): array {
        self::$db->prepared_query('SHOW GLOBAL STATUS');
        return self::$db->to_array('Variable_name', MYSQLI_ASSOC);
    }

    public function globalVariables(): array {
        self::$db->prepared_query('SHOW GLOBAL VARIABLES');
        return self::$db->to_array('Variable_name', MYSQLI_ASSOC);
    }

    public function version(): string {
        return (string)self::$db->scalar("SELECT @@version");
    }

    public function selectQuery(string $tableName): string {
        self::$db->prepared_query("
            SELECT concat(column_name, ' /* ', data_type, ' */') AS c
            FROM information_schema.columns
            WHERE table_schema = ?
                AND table_name = ?
            ORDER BY ordinal_position
            ", MYSQL_DB, $tableName
        );
        return "SELECT " . implode(",\n    ", self::$db->collect(0)) . "\nFROM $tableName\nWHERE --";
    }

    public function primaryKeyExists(string $tableName, string $columnName): bool {
        return (bool)self::$db->scalar("
            SELECT 1
            FROM information_schema.columns
            WHERE table_schema = ?
                AND table_name = ?
                AND column_name = ?
            ", MYSQL_DB, $tableName, $columnName
        );
    }

    /**
     * Check that the structures of two tables are identical (to ensure we can
     * select a row from one table and copy it to the other.
     * They must have the same number of columns, and each column must have
     * identical datatypes. Primary and foreign keys are not considered (and do
     * not have to be identical).
     *
     * @return array [bool, string]
     *
     * if the returned bool is false, the string contains a readable explanation of why they differ
     * if true, the string contains a comma-separated list of column names
     * (for copying the row from one table to another)
     */
    public function checkStructureMatch(string $schema, string $source, string $destination): array {
        $sql = '
            SELECT column_name, column_type, is_nullable
            FROM information_schema.columns
            WHERE table_schema = ?
                AND table_name = ?
            ORDER BY 1
        ';
        self::$db->prepared_query($sql, $schema, $source);
        $t1 = self::$db->to_array(false, MYSQLI_BOTH);
        $n1 = count($t1);

        self::$db->prepared_query($sql, $schema, $destination);
        $t2 = self::$db->to_array(false, MYSQLI_BOTH);
        $n2 = count($t2);

        if (!$n1) {
            return [false, "No such source table $source"];
        } elseif (!$n2) {
            return [false, "No such destination table $destination"];
        } elseif ($n1 != $n2) {
            // tables do not have the same number of columns
            return [false, "$source and $destination column count mismatch ($n1 != $n2)"];
        }

        $column = [];
        for ($i = 0; $i < $n1; ++$i) {
            // a column does not have the same name or datatype
            if (
                strtolower($t1[$i][0]) != strtolower($t2[$i][0])
                || $t1[$i][1] != $t2[$i][1]
                || $t1[$i][2] != $t2[$i][2]
            ) {
                return [false, "{$source}: column {$t1[$i][0]} name, datatype or nullable mismatch {$t1[$i][0]}:{$t2[$i][0]} {$t1[$i][1]}:{$t2[$i][1]} {$t1[$i][2]}:{$t2[$i][2]}"];
            }
            $column[] = $t1[$i][0];
        }
        return [true, implode(', ', $column)];
    }

    /**
     * Soft delete a row from a table <t> by inserting it into deleted_<t> and optionally deleting from <t>
     * The selection of the row is handled by the name of the primary key column and value.
     */
    public function softDelete(
        string $schema,
        string $table,
        string $pkColumn,
        int $pkId,
        bool $delete = true,
    ): array {
        $softDeleteTable = "deleted_$table";
        [$ok, $message] = $this->checkStructureMatch($schema, $table, $softDeleteTable);
        if (!$ok) {
            return [$ok, $message];
        }
        $columnList = $message;

        $sql = "INSERT INTO $softDeleteTable
                  ($columnList)
            SELECT $columnList
            FROM $table
            WHERE $pkColumn = ?";
        try {
            self::$db->prepared_query($sql, $pkId);
            if (self::$db->affected_rows() == 0) {
                return [false, "condition selected 0 rows"];
            }
        } catch (DB\MysqlDuplicateKeyException) {
            ; // do nothing, for some reason it was already deleted
        }

        if (!$delete) {
            return [true, "rows affected: " . self::$db->affected_rows()];
        }

        $sql = "DELETE FROM $table WHERE $pkColumn = ?";
        self::$db->prepared_query($sql, $pkId);
        return [true, "rows deleted: " . self::$db->affected_rows()];
    }

    /**
     * Calculate page and SQL limit
     * @param int $pageSize records per page
     * @param int $page current page or a falsey value to fetch from $_REQUEST
     */
    public static function pageLimit(int $pageSize, int $page = 0): array {
        if (!$page) {
            $page = max(1, (int)($_REQUEST['page'] ?? 0));
        }

        return [$page, $pageSize, $pageSize * ($page - 1)];
    }

    /**
     * How many queries have been runnning for more than 20 minutes?
     */
    public function longRunning(): int {
        return (int)self::$db->scalar("
            SELECT count(*)
            FROM performance_schema.processlist
            WHERE COMMAND NOT IN ('Sleep')
                AND TIME > ?
            ", MYSQL_SLOW_QUERY_TIMEOUT
        );
    }

    public function redundantIndexList(): array {
        self::$db->prepared_query("
            SELECT sri.table_name,
                concat(sri.dominant_index_name, ' (', sri.dominant_index_columns, ')')   AS covering_index,
                concat(sri.redundant_index_name, ' (', sri.redundant_index_columns, ')') AS redundant_index,
                coalesce(c.rows_read, 0) AS covering_read,
                coalesce(r.rows_read, 0) AS redundant_read
            FROM sys.schema_redundant_indexes sri
            LEFT JOIN information_schema.index_statistics c
                ON (c.table_name = sri.table_name and c.index_name = sri.dominant_index_name)
            LEFT JOIN information_schema.index_statistics r
                ON (r.table_name = sri.table_name and r.index_name = sri.redundant_index_name)
            WHERE sri.table_schema = ?
            ORDER BY table_name, covering_index, redundant_index
            ", MYSQL_DB
        );
        $list = [];
        foreach (self::$db->to_array(false, MYSQLI_ASSOC) as $r) {
            $r['covering_read'] = (int)$r['covering_read'];
            $r['redundant_read'] = (int)$r['redundant_read'];
            $list[] = $r;
        }
        return $list;
    }

    public function unusedIndexList(): array {
        self::$db->prepared_query("
             SELECT sui.object_name AS table_name,
                sui.index_name,
                group_concat(
                    concat(s.column_name, ' {', s.cardinality, '}')
                    ORDER BY s.seq_in_index
                    SEPARATOR ', '
                ) AS column_list
            FROM sys.schema_unused_indexes sui
            INNER JOIN information_schema.statistics s on (
                s.table_schema = sui.object_schema
                AND s.table_name = sui.object_name
                AND s.index_name = sui.index_name
            )
            WHERE sui.object_schema = ?
            GROUP BY table_name, index_name
            ORDER BY table_name, index_name
            ", MYSQL_DB
        );
        return self::$db->to_array(false, MYSQLI_ASSOC);
    }
}
