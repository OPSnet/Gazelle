<?php

namespace Gazelle\Manager;

class Request extends \Gazelle\Base {

    public function findById(int $requestId) {
        $id = $this->db->scalar("
            SELECT ID FROM requests WHERE ID = ?
            ", $requestId
        );
        return $id ? new \Gazelle\Request($id) : null;
    }
}
