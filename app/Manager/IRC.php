<?php

namespace Gazelle\Manager;

class IRC extends \Gazelle\Base {
    public function create(string $name, int $sort, int $minLevel, array $classList): int {
        self::$db->prepared_query("
            INSERT INTO irc_channels
                   (Name, Sort, MinLevel, Classes)
            VALUES (?,    ?,    ?,        ?)
            ", $name, $sort, $minLevel, implode(',', $classList)
        );
        return self::$db->inserted_id();
    }

    public function list(): array {
        self::$db->prepared_query("
            SELECT ID AS id,
                Name AS name,
                Sort AS sort,
                MinLevel AS min_level,
                Classes AS classes
            FROM irc_channels
            ORDER BY Sort
        ");
        $list = self::$db->to_array(false, MYSQLI_ASSOC, false);
        foreach ($list as &$row) {
            $row['class_list'] = explode(',', $row['classes']);
        }
        unset($row);
        return $list;
    }

    public function modify (int $id, string $name, int $sort, int $minLevel, array $classList): int {
        self::$db->prepared_query("
            UPDATE irc_channels SET
                Name = ?,
                Sort = ?,
                MinLevel = ?,
                Classes = ?
            WHERE ID = ?
            ", $name, $sort, $minLevel, implode(',', $classList), $id
        );
        return self::$db->affected_rows();
    }
}
