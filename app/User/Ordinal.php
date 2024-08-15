<?php

namespace Gazelle\User;

/**
 * Ordinals are just numeric values, and this class offers a
 * generic wrapper to deal with the storage and manipulation.
 *
 * Ordinals are be used in a number of places, such as
 *  - number of paid personal collages
 *  - default request bounty when creating
 *  - default request bounty when voting (may be different)
 */

class Ordinal extends \Gazelle\BaseUser {
    final protected const CACHE_KEY = 'u_ord_%s';

    protected array $info;

    public function flush(): static {
        self::$cache->delete_value(sprintf(self::CACHE_KEY, $this->id()));
        unset($this->info);
        return $this;
    }

    public function info(): array {
        if (!isset($this->info)) {
            $key = sprintf(self::CACHE_KEY, $this->id());
            $info = self::$cache->get_value($key);
            if ($info === false) {
                self::$db->prepared_query("
                    SELECT uo.name,
                        uo.default_value,
                        uo.default_value as value
                        FROM user_ordinal uo
                        WHERE NOT EXISTS (
                            SELECT 1 FROM user_has_ordinal uho
                            WHERE uho.user_id = ?
                                AND uho.user_ordinal_id = uo.user_ordinal_id
                        )
                    UNION ALL
                    SELECT uo.name,
                        uo.default_value,
                        uho.value
                    FROM user_ordinal uo
                    LEFT JOIN user_has_ordinal uho USING (user_ordinal_id)
                    WHERE uho.user_id = ?
                    ", $this->id(), $this->id()
                );
                $info = self::$db->to_array('name', MYSQLI_ASSOC, false);
                self::$cache->cache_value($key, $info, 86400);
            }
            $this->info = $info;
        }
        return $this->info;
    }

    public function defaultValue(string $name): int {
        return $this->info()[$name]['default_value'];
    }

    public function value(string $name): int {
        return $this->info()[$name]['value'];
    }

    // manipulators

    public function increment(string $name, int $delta): int {
        return $this->set($name, $this->value($name) + $delta);
    }

    public function set(string $name, int $value): int {
        if (!isset($this->info()[$name])) {
            // does not exist
            return 0;
        }
        if ($value === $this->defaultValue($name)) {
            // if the proposed value matches the default value,
            // there is no need to store it: when fetched,
            // the default value will be returned.
            // This avoids storing rows for all the users who
            // remain at the default.
            return $this->remove($name);
        }

        // have to create or modify the existing row
        self::$db->prepared_query("
            INSERT INTO user_has_ordinal
                  (user_ordinal_id, user_id, value)
            SELECT user_ordinal_id, ?,       ?
            FROM user_ordinal
            WHERE name = ?
            ON DUPLICATE KEY UPDATE value = ?
            ", $this->id(), $value, $name, $value
        );
        $affected = self::$db->affected_rows();
        $this->flush();
        return $affected;
    }

    public function remove($name): int {
        self::$db->prepared_query("
            DELETE FROM user_has_ordinal
            WHERE user_id = ?
                AND user_ordinal_id = (
                    SELECT user_ordinal_id
                    FROM user_ordinal
                    WHERE name = ?
                )
            ", $this->id(), $name
        );
        $affected = self::$db->affected_rows();
        $this->flush();
        return $affected;
    }
}
