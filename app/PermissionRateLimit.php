<?php

namespace Gazelle;

class PermissionRateLimit {
    /** @var \DB_MYSQL */
    private $db;
    /** @var \CACHE */
    private $cache;

    public function __construct(\DB_MYSQL $db, \CACHE $cache) {
        $this->db = $db;
        $this->cache = $cache;
    }

    public function list() {
         $this->db->prepared_query('
            SELECT p.ID, p.Name, prl.factor, prl.overshoot
            FROM permission_rate_limit prl
            INNER JOIN permissions p ON (p.ID = prl.permission_id)
            ORDER BY p.Level
        ');
        return $this->db->to_array('ID', MYSQLI_ASSOC, false);
    }

    public function save($id, $factor, $overshoot) {
         $this->db->prepared_query('
            INSERT INTO permission_rate_limit
                   (permission_id, factor, overshoot)
            VALUES (?,             ?,      ?)
            ', $id, $factor, $overshoot
        );
        return $this->db->affected_rows();
    }

    public function remove($id) {
         $this->db->prepared_query('
             DELETE FROM permission_rate_limit WHERE permission_id = ?
            ', $id
        );
        return $this->db->affected_rows();
    }

    public function safeFactor($factor, $userId) {
         $this->db->prepared_query('
            SELECT factor
            FROM permission_rate_limit prl
            INNER JOIN permissions p ON (p.ID = prl.permission_id)
            INNER JOIN users_main um ON (um.PermissionID = prl.permission_id)
            WHERE um.ID = ?
            ', $userId
        );
        if (!$this->db->has_results()) {
            return true;
        }
        list($classFactor) = $this->db->next_record(MYSQLI_NUM, false);
        return $factor <= $classFactor;
    }

    public function safeOvershoot($overshoot, $userId) {
         $this->db->prepared_query('
            SELECT overshoot
            FROM permission_rate_limit prl
            INNER JOIN permissions p ON (p.ID = prl.permission_id)
            INNER JOIN users_main um ON (um.PermissionID = prl.permission_id)
            WHERE um.ID = ?
            ', $userId
        );
        if (!$this->db->has_results()) {
            return true;
        }
        list($classOvershoot) = $this->db->next_record(MYSQLI_NUM, false);
        return $overshoot <= $classOvershoot;
    }
}
