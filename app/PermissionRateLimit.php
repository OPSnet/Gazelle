<?php

namespace Gazelle;

class PermissionRateLimit extends Base {

    public function list() {
         self::$db->prepared_query('
            SELECT p.ID, p.Name, prl.factor, prl.overshoot
            FROM permission_rate_limit prl
            INNER JOIN permissions p ON (p.ID = prl.permission_id)
            ORDER BY p.Level
        ');
        return self::$db->to_array('ID', MYSQLI_ASSOC, false);
    }

    public function save($id, $factor, $overshoot) {
         self::$db->prepared_query('
            INSERT INTO permission_rate_limit
                   (permission_id, factor, overshoot)
            VALUES (?,             ?,      ?)
            ', $id, $factor, $overshoot
        );
        return self::$db->affected_rows();
    }

    public function remove($id) {
         self::$db->prepared_query('
             DELETE FROM permission_rate_limit WHERE permission_id = ?
            ', $id
        );
        return self::$db->affected_rows();
    }

    public function safeFactor(\Gazelle\User $user) {
         self::$db->prepared_query('
            SELECT factor
            FROM permission_rate_limit prl
            INNER JOIN permissions p ON (p.ID = prl.permission_id)
            INNER JOIN users_main um ON (um.PermissionID = prl.permission_id)
            WHERE um.ID = ?
            ', $user->id()
        );
        if (!self::$db->has_results()) {
            return true;
        }
        list($classFactor) = self::$db->next_record(MYSQLI_NUM, false);
        return $user->downloadSnatchFactor() <= $classFactor;
    }

    public function safeOvershoot(\Gazelle\User $user) {
         self::$db->prepared_query('
            SELECT overshoot
            FROM permission_rate_limit prl
            INNER JOIN permissions p ON (p.ID = prl.permission_id)
            INNER JOIN users_main um ON (um.PermissionID = prl.permission_id)
            WHERE um.ID = ?
            ', $user->id()
        );
        if (!self::$db->has_results()) {
            return true;
        }
        list($classOvershoot) = self::$db->next_record(MYSQLI_NUM, false);
        return $user->torrentRecentDownloadCount() <= $classOvershoot;
    }
}
