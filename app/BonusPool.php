<?php

namespace Gazelle;

class BonusPool extends Base {
    protected $id;

    const CACHE_SENT = 'bonuspool_sent_%d';

    public function __construct (int $id) {
        $this->id = $id;
    }

    public function contribute(int $user_id, $value_recv, $value_sent): int {
        self::$db->prepared_query("
            INSERT INTO bonus_pool_contrib
                   (bonus_pool_id, user_id, amount_recv, amount_sent)
            VALUES (?,             ?,       ?,           ?)
            ", $this->id, $user_id, $value_recv, $value_sent
        );
        self::$db->prepared_query("
            UPDATE bonus_pool SET
                total = total + ?
            WHERE bonus_pool_id = ?
            ", $value_sent, $this->id
        );
        self::$cache->delete_value(sprintf(self::CACHE_SENT, $this->id));
        return self::$db->affected_rows();
    }

    public function total(): int {
        $key = sprintf(self::CACHE_SENT, $this->id);
        if (($total = self::$cache->get_value($key)) === false) {
            $total = self::$db->scalar("
                SELECT total FROM bonus_pool WHERE bonus_pool_id = ?
                ", $this->id
            ) ?? 0;
            self::$cache->cache_value($key, $total, 6 * 3600);
        }
        return $total;
    }
}
