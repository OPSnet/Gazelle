<?php

namespace Gazelle\Manager;

class PM extends \Gazelle\Base {

    protected const ID_KEY = 'zz_pm_%d_%d';

    protected \Gazelle\User $user;

    public function __construct(\Gazelle\User $user) {
        parent::__construct();
        $this->user = $user;
    }

    public function findById(int $pmId): ?\Gazelle\PM {
        $key = sprintf(self::ID_KEY, $pmId, $this->user->id());
        $id = $this->cache->get_value($key);
        if ($id === false) {
            $id = $this->db->scalar("
                SELECT cu.ConvID
                FROM pm_conversations_users cu
                WHERE cu.ConvID = ?
                    AND cu.UserID = ?
                ", $pmId, $this->user->id()
            );
            if (!is_null($id)) {
                $this->cache->cache_value($key, $id, 0);
            }
        }
        return $id ? new \Gazelle\PM($id, $this->user) : null;
    }
}
