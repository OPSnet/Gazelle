<?php

namespace Gazelle;

class Stylesheet extends Base {

    private $stylesheets;

    public function __construct() {
        parent::__construct();
        if (($this->stylesheets = $this->cache->get_value('stylesheets')) === false) {
            $this->db->query("
                SELECT
                    ID,
                    lower(replace(Name, ' ', '_')) AS Name,
                    Name AS ProperName
                FROM stylesheets
                ORDER BY ID ASC
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
