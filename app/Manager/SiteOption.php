<?php

namespace Gazelle\Manager;

class SiteOption extends \Gazelle\Base {

    const CACHE_KEY = 'site_option_%s';

    public function findValueByName(string $name): string {
        $key = sprintf(self::CACHE_KEY, $name);
        if (($value = $this->cache->get_value($key)) === false) {
            $value = $this->db->scalar("
                SELECT Value FROM site_options WHERE Name = ?
                ", 'bonus-discount'
            );
            $this->cache->cache_value($key, $name, $value, 86400 * 30);
        }
        return $value;
    }

    /**
     * Get the list of current site options.
     *
     * @return array of [id, name, value, comment]
     */
    public function list(): array {
        $this->db->prepared_query("
            SELECT ID   AS id,
                Name    AS name,
                Value   AS value,
                Comment AS comment
            FROM site_options
            ORDER BY Name
        ");
        return $this->db->to_array('name', MYSQLI_ASSOC, false);
    }

    /**
     * Create a new option key/value pair.
     *
     * @param string Name of option
     * @param string Value of option
     * @param string Mandatory comment
     * @return int ID of option (or null on failure e.g. duplicate name)
     */
    public function create(string $name, string $value, string $comment): ?int {
        try {
            $this->db->prepared_query('
                INSERT INTO site_options
                       (Name, Value, Comment)
                VALUES (?,    ?,     ?)
                ', $name, $value, $comment
            );
        } catch (\DB_MYSQL_DuplicateKeyException $e) {
            return null;
        }
        $this->cache->cache_value(sprintf(self::CACHE_KEY, $name), $value);
        return $this->db->inserted_id();
    }

    /**
     * Modify an option (both the name and value may be changed)
     *
     * @param int ID of option
     * @param string Name of option
     * @param string Value of option
     * @param string Mandatory comment
     * @return int 1 if option was updated, otherwise 0
     */
    public function modify(int $id, string $name, string $value, string $comment): int {
        $oldName = $this->db->scalar("
            SELECT Name FROM site_options WHERE ID = ?
            ", $id
        );
        $this->db->prepared_query('
            UPDATE site_options SET
                Name = ?, Value = ?, Comment = ?
            WHERE ID = ?
            ', $name, $value, $comment, $id
        );
        $this->cache->delete_value(sprintf(self::CACHE_KEY, $oldName));
        $this->cache->cache_value(sprintf(self::CACHE_KEY, $name), $value);
        return $this->db->affected_rows();
    }


    /**
     * Remove an option by name
     *
     * @param string Name of option
     * @return int 1 if option was removed, otherwise 0
     */
    public function remove(string $name): int {
        $this->db->prepared_query("
            DELETE FROM site_options WHERE Name = ?
            ", $name
        );
        $this->cache->delete_value(sprintf(self::CACHE_KEY, $name));
        return $this->db->affected_rows();
    }
}
