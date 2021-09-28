<?php

namespace Gazelle\Manager;

class Request extends \Gazelle\Base {

    protected const ID_KEY = 'zz_r_%d';

    public function findById(int $requestId): ?\Gazelle\Request {
        $key = sprintf(self::ID_KEY, $requestId);
        $id = $this->cache->get_value($key);
        if ($id === false) {
            $id = $this->db->scalar("
                SELECT ID FROM requests WHERE ID = ?
                ", $requestId
            );
            if (!is_null($id)) {
                $this->cache->cache_value($key, $id, 0);
            }
        }
        return $id ? new \Gazelle\Request($id) : null;
    }
}
