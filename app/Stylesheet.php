<?php

namespace Gazelle;

class Stylesheet {
    /** @var \DB_MYSQL */
    private $db;

    /** @var \CACHE */
    private $cache;

    private $stylesheets;

    public function __construct(\DB_MYSQL $db, \CACHE $cache) {
        $this->db = $db;
        $this->cache = $cache;

        if (($this->stylesheets = $this->cache->get_value('stylesheets')) === false) {
            $this->db->query("
                SELECT
                    ID,
                    lower(replace(Name, ' ', '_')) AS Name,
                    Name AS ProperName
                FROM stylesheets
                ORDER BY ID DESC
            ");
            $this->stylesheets = $this->db->to_array('ID', MYSQLI_BOTH);
            $this->cache->cache_value('stylesheets', $this->stylesheets, 86400 * 7);
        }
    }

    public function list () {
        return $this->stylesheets;
    }

    public function getName($id) {
        return $this->stylesheets[$id]['Name'];
    }
}
