<?php

namespace Gazelle\Manager;

class UserclassRateLimit extends \Gazelle\Base {
    public function list(): array {
         self::$db->prepared_query('
            SELECT p.ID, p.Name, prl.factor, prl.overshoot
            FROM permission_rate_limit prl
            INNER JOIN permissions p ON (p.ID = prl.permission_id)
            ORDER BY p.Level
        ');
        return self::$db->to_array(false, MYSQLI_ASSOC, false);
    }

    public function save(int $id, float $factor, int $overshoot): int {
         self::$db->prepared_query('
            INSERT INTO permission_rate_limit
                   (permission_id, factor, overshoot)
            VALUES (?,             ?,      ?)
            ', $id, $factor, $overshoot
        );
        return self::$db->affected_rows();
    }

    public function remove(int $id): int {
         self::$db->prepared_query('
             DELETE FROM permission_rate_limit WHERE permission_id = ?
            ', $id
        );
        return self::$db->affected_rows();
    }
}
