<?php

namespace Gazelle\Manager;

class SiteOption extends \Gazelle\Base {
    final const CACHE_KEY = 'site_option_%s';

    public function findValueByName(string $name): ?string {
        $key = sprintf(self::CACHE_KEY, $name);
        $value = self::$cache->get_value($key);
        if ($value === false) {
            $value = self::$db->scalar(
                "SELECT Value FROM site_options WHERE Name = ?",
                $name
            );
            if (!is_null($value)) {
                self::$cache->cache_value($key, $value, 86400 * 30);
            }
        }
        return $value;
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
        return self::$db->to_array('name', MYSQLI_ASSOC, false);
    }

    /**
     * Create a new option key/value pair.
     *
     * @return int ID of option (or null on failure e.g. duplicate name)
     */
    public function create(string $name, string $value, string $comment): ?int {
        try {
            self::$db->prepared_query('
                INSERT INTO site_options
                       (Name, Value, Comment)
                VALUES (?,    ?,     ?)
                ', $name, $value, $comment
            );
        } catch (\Gazelle\DB\Mysql_DuplicateKeyException) {
            return null;
        }
        self::$cache->cache_value(sprintf(self::CACHE_KEY, $name), $value);
        return self::$db->inserted_id();
    }

    /**
     * Modify an option (both the name and value may be changed)
     *
     * @return int 1 if option was updated, otherwise 0
     */
    public function modify(int $id, string $name, string $value, string $comment): int {
        $oldName = self::$db->scalar("
            SELECT Name FROM site_options WHERE ID = ?
            ", $id
        );
        self::$db->prepared_query('
            UPDATE site_options SET
                Name = ?, Value = ?, Comment = ?
            WHERE ID = ?
            ', $name, $value, $comment, $id
        );
        self::$cache->delete_value(sprintf(self::CACHE_KEY, $oldName));
        self::$cache->cache_value(sprintf(self::CACHE_KEY, $name), $value);
        return self::$db->affected_rows();
    }


    /**
     * Remove an option by name
     *
     * @return int 1 if option was removed, otherwise 0
     */
    public function remove(string $name): int {
        self::$db->prepared_query("
            DELETE FROM site_options WHERE Name = ?
            ", $name
        );
        self::$cache->delete_value(sprintf(self::CACHE_KEY, $name));
        return self::$db->affected_rows();
    }
}
