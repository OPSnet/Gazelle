<?php

namespace Gazelle;

class BonusPool extends BaseObject {
    final public const tableName  = 'bonus_pool';
    final public const pkName     = 'bonus_pool_id';
    final public const CACHE_SENT = 'bonuspool_sent_%d';

    public function __construct(
        public readonly int $id,
    ) {}

    public function flush(): static {
        self::$cache->delete_multi([
            sprintf(self::CACHE_SENT, $this->id),
            Manager\Bonus::CACHE_OPEN_POOL,
        ]);
        unset($this->info);
        return $this;
    }

    public function link(): string {
        return "";
    }

    public function location(): string {
        return "";
    }

    public function contribute(User $user, $valueReceived, $valueSent): int {
        self::$db->prepared_query("
            INSERT INTO bonus_pool_contrib
                   (bonus_pool_id, user_id, amount_recv, amount_sent)
            VALUES (?,             ?,       ?,           ?)
            ", $this->id, $user->id, $valueReceived, $valueSent
        );
        $affected = self::$db->affected_rows();
        self::$db->prepared_query("
            UPDATE bonus_pool SET
                total = total + ?
            WHERE bonus_pool_id = ?
            ", $valueSent, $this->id
        );
        $affected += self::$db->affected_rows();
        $this->flush();
        return $affected;
    }

    public function total(): int {
        $key = sprintf(self::CACHE_SENT, $this->id);
        $total = self::$cache->get_value($key);
        if ($total === false) {
            $total = (int)self::$db->scalar("
                SELECT total FROM bonus_pool WHERE bonus_pool_id = ?
                ", $this->id
            );
            self::$cache->cache_value($key, $total, 6 * 3600);
        }
        return $total;
    }

    public function remove(): int {
        self::$db->prepared_query("
            DELETE FROM bonus_pool_contrib WHERE bonus_pool_id = ?
            ", $this->id
        );
        $affected = self::$db->affected_rows();
        self::$db->prepared_query("
            DELETE FROM bonus_pool WHERE bonus_pool_id = ?
            ", $this->id
        );
        $affected += self::$db->affected_rows();
        $this->flush();
        return $affected;
    }
}
