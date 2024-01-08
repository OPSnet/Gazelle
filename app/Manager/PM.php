<?php

namespace Gazelle\Manager;

class PM extends \Gazelle\BaseUser {
    final public const tableName  = 'pm_conversations_users';
    protected const ID_KEY = 'zz_pm_%d_%d';

    public function flush(): static     { $this->user()->flush(); return $this; }
    public function link(): string      { return $this->user()->link(); }
    public function location(): string  { return $this->user()->location(); }

    public function findById(int $pmId): ?\Gazelle\PM {
        $key = sprintf(self::ID_KEY, $pmId, $this->user->id());
        $id = self::$cache->get_value($key);
        if ($id === false) {
            $id = (int)self::$db->scalar("
                SELECT pcu.ConvID
                FROM pm_conversations_users pcu
                WHERE pcu.ConvID = ?
                    AND pcu.UserID = ?
                ", $pmId, $this->user->id()
            );
            if ($id) {
                self::$cache->cache_value($key, $id, 7200);
            }
        }
        return $id ? new \Gazelle\PM($id, $this->user) : null;
    }

    public function findByPostId(int $postId): ?\Gazelle\PM {
        $id = (int)self::$db->scalar("
            SELECT pcu.ConvID
            FROM pm_conversations_users pcu
            INNER JOIN pm_messages      pm USING (ConvID)
            WHERE pcu.UserID = ?
                AND pm.ID = ?
            ", $this->user->id(), $postId
        );
        return $id ? new \Gazelle\PM($id, $this->user) : null;
    }
}
