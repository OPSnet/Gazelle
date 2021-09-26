<?php

namespace Gazelle\Manager;

class PM extends \Gazelle\Base {

    protected \Gazelle\User $user;

    public function __construct(\Gazelle\User $user) {
        parent::__construct();
        $this->user = $user;
    }

    public function findById(int $id) {
        $id = $this->db->scalar("
            SELECT cu.ConvID
            FROM pm_conversations_users cu
            WHERE cu.ConvID = ?
                AND cu.UserID = ?
            ", $id, $this->user->id()
        );
        return is_null($id) ? null : new \Gazelle\PM($id, $this->user);
    }
}
