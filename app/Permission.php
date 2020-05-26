<?php

namespace Gazelle;

class Permission extends Base {
    protected $list;

    const CACHE_KEY = 'permissions';

    public function list() {
        if (($this->list = $this->cache->get_value(self::CACHE_KEY)) === false) {
            $this->db->prepared_query('
                SELECT ID, Name
                FROM permissions
                WHERE Secondary = 0
                ORDER BY level
            ');
            $this->list = $this->db->to_array('ID', MYSQLI_ASSOC, false);
            $this->cache->cache_value(self::CACHE_KEY, $this->list, 84600 * 7);
        }
        return $this->list;
    }
}
