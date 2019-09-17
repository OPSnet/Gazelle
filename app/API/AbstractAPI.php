<?php

namespace Gazelle\API;

abstract class AbstractAPI {
    protected $db;
    protected $cache;
    protected $config;

    public function __construct(\DB_MYSQL $db, \CACHE $cache, array $config) {
        $this->db = $db;
        $this->cache = $cache;
        $this->config = $config;
    }

    abstract public function run();
}
