<?php

namespace Gazelle\Manager;

class PM extends \Gazelle\BaseUser {
    protected const ID_KEY = 'zz_pm_%d_%d';

    public function flush(): PM         { $this->user()->flush(); return $this; }
    public function link(): string      { return $this->user()->link(); }
    public function location(): string  { return $this->user()->location(); }
    public function tableName(): string { return 'pm_conversations_users'; }

    public function findById(int $pmId): ?\Gazelle\PM {
        $key = sprintf(self::ID_KEY, $pmId, $this->user->id());
        $id = self::$cache->get_value($key);
        if ($id === false) {
            $id = self::$db->scalar("
                SELECT cu.ConvID
                FROM pm_conversations_users cu
                WHERE cu.ConvID = ?
                    AND cu.UserID = ?
                ", $pmId, $this->user->id()
            );
            if (!is_null($id)) {
                self::$cache->cache_value($key, $id, 7200);
            }
        }
        return $id ? new \Gazelle\PM($id, $this->user) : null;
    }
}
