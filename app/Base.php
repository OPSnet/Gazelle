<?php

namespace Gazelle;

class Base {
    /** @var \DB_MYSQL */
    protected $db;

    /** @var \CACHE */
    protected $cache;

    public function __construct() {
        $this->db = new \DB_MYSQL;
        $this->cache = \G::$Cache;
    }
}
