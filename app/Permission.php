<?php

namespace Gazelle;

class Permission {
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
            SELECT ID, Name
            FROM permissions
            WHERE Secondary = 0
            ORDER BY level
        ');
        return $this->db->to_array('ID', MYSQLI_ASSOC, false);
    }
}
