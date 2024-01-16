<?php

namespace Gazelle\Manager;

class StaffGroup extends \Gazelle\BaseManager {
    final public const LIST_KEY = 'stgroup';
    final public const ID_KEY   = 'zz_sg_%d';

    public function create(int $sequence, string $name): \Gazelle\StaffGroup {
        self::$db->prepared_query("
            INSERT INTO staff_groups
                   (Sort, Name)
            Values (?,    ?)
            ", $sequence, $name
        );
        return new \Gazelle\StaffGroup(self::$db->inserted_id());
    }

    public function findById(int $staffGroupId): ?\Gazelle\StaffGroup {
        $key = sprintf(self::ID_KEY, $staffGroupId);
        $id = self::$cache->get_value($key);
        if ($id === false) {
            $id = self::$db->scalar("
                SELECT ID FROM staff_groups WHERE ID = ?
                ", $staffGroupId
            );
            if (!is_null($id)) {
                self::$cache->cache_value($key, $id, 7200);
            }
        }
        return $id ? new \Gazelle\StaffGroup($id) : null;
    }

    public function groupList(): array {
        self::$db->prepared_query("
            SELECT ID AS id,
                Sort AS sequence,
                Name AS name
            FROM staff_groups
            ORDER BY Sort
        ");
        return self::$db->to_array(false, MYSQLI_ASSOC, false);
    }
}
