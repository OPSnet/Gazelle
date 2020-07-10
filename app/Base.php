<?php

namespace Gazelle;

class Base {
    /** @var \DB_MYSQL */
    protected $db;

    /** @var \CACHE */
    protected $cache;

    public function __construct() {
        $this->cache = \G::$Cache;
        $this->db = \G::$DB;
    }
}
