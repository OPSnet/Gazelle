<?php

namespace Gazelle\Manager;

class SiteOption extends \Gazelle\Base {
    final public const CACHE_KEY = 'site_option_%s';

    /**
     * Create a new option key/value pair.
     *
     * @return int ID of option (or null on failure e.g. duplicate name)
     */
    public function createOption(string $name, string $value, string $comment): ?int {
        $optionId = null;
        try {
            self::$db->prepared_query('
                INSERT INTO site_options
                       (Name, Value, Comment)
                VALUES (?,    ?,     ?)
                ', $name, $value, $comment
            );
            $optionId = self::$db->inserted_id();
            self::$cache->cache_value(sprintf(self::CACHE_KEY, $name), $value);
        } catch (\Gazelle\DB\MysqlDuplicateKeyException) {
            ;
        }
        return $optionId;
    }

    public function findValueByName(string $name): ?string {
        $key = sprintf(self::CACHE_KEY, $name);
        $value = self::$cache->get_value($key);
        if ($value === false) {
            $value = self::$db->scalar(
                "SELECT Value FROM site_options WHERE Name = ?",
                $name
            );
            if (!is_null($value)) {
                self::$cache->cache_value($key, $value, 0);
            }
        }
        return $value;
    }

    public function freeTokenSize(): int {
        return 1024 * 1024 * (int)$this->findValueByName('fl-token-size');
    }

    /**
     * Get the list of current site options.
     *
     * @return array of [id, name, value, comment]
     */
    public function list(): array {
        self::$db->prepared_query("
            SELECT ID   AS id,
                Name    AS name,
                Value   AS value,
                Comment AS comment
            FROM site_options
            ORDER BY Name
        ");
        return self::$db->to_array('name', MYSQLI_ASSOC);
    }

    /**
     * Set option $name's value to $value
     *
     * @return int 1 if option was updated, otherwise 0
     */
    public function modifyOption(string $name, string $value): int {
        self::$db->prepared_query('
            UPDATE site_options SET Value = ? WHERE Name = ?
            ', $value, $name
        );
        $affected = self::$db->affected_rows();
        self::$cache->cache_value(sprintf(self::CACHE_KEY, $name), $value, 0);
        return $affected;
    }

    /**
     * Remove an option by name
     *
     * @return int 1 if option was removed, otherwise 0
     */
    public function removeOptionByName(string $name): int {
        self::$db->prepared_query("
            DELETE FROM site_options WHERE Name = ?
            ", $name
        );
        self::$cache->delete_value(sprintf(self::CACHE_KEY, $name));
        return self::$db->affected_rows();
    }
}
