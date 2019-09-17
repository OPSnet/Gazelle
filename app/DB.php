<?php

namespace Gazelle;

class DB {
    /** @var \DB_MYSQL */
    private $db;

    /** @var \CACHE */
    private $cache;

    public function __construct (\DB_MYSQL $db, \CACHE $cache) {
        $this->db = $db;
        $this->cache = $cache;
    }

    /**
     * Soft delete a row from a table <t> by inserting it into deleted_<t> and then delete from <t>
     * @param string $schema the schema name
     * @param string $table the table name
     * @param array $condition Must be an array of arrays, e.g. [[column_name, column_value]] or [[col1, val1], [col2, val2]]
     *                         Will be used to identify the row (or rows) to delete
     * @return array 2 elements, true/false and message if false
     */
    public function soft_delete($schema, $table, array $condition) {
        $sql = 'SELECT column_name, column_type FROM information_schema.columns WHERE table_schema = ? AND table_name = ? ORDER BY 1';
        $this->db->prepared_query($sql, $schema, $table);
        $t1 = $this->db->to_array();
        $n1 = count($t1);

        $soft_delete_table = 'deleted_' . $table;
        $this->db->prepared_query($sql, $schema, $soft_delete_table);
        $t2 = $this->db->to_array();
        $n2 = count($t2);

        if (!$n1) {
            return [false, "No such table $table"];
        }
        elseif (!$n2) {
            return [false, "No such table $soft_delete_table"];
        }
        elseif ($n1 != $n2) {
            // tables do not have the same number of columns
            return [false, "$table and $soft_delete_table column count mismatch ($n1 != $n2)"];
        }

        $column = [];
        for ($i = 0; $i < $n1; ++$i) {
            // a column does not have the same name or datatype
            if (strtolower($t1[$i][0]) != strtolower($t2[$i][0]) || $t1[$i][1] != $t2[$i][1]) {
                return [false, "column {$t1[$i][0]} name or datatype mismatch {$t1[$i][0]}:{$t2[$i][0]} {$t1[$i][1]}:{$t2[$i][1]}"];
            }
            $column[] = $t1[$i][0];
        }
        $column_list = implode(', ', $column);
        $condition_list = implode(' AND ', array_map(function ($c) {return "{$c[0]} = ?";}, $condition));
        $arg_list = array_map(function ($c) {return $c[1];}, $condition);

        $sql = "INSERT INTO $soft_delete_table
			      ($column_list)
			SELECT $column_list
			FROM $table
			WHERE $condition_list";
        $this->db->prepared_query_array($sql, $arg_list);
        if ($this->db->affected_rows() == 0) {
            return [false, "condition selected 0 rows"];
        }

        $sql = "DELETE FROM $table WHERE $condition_list";
        $this->db->prepared_query_array($sql, $arg_list);
        return [true, "rows deleted: " . $this->db->affected_rows()];
    }
}
