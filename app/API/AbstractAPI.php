<?php

namespace Gazelle\API;

abstract class AbstractAPI {
    protected $db;
    protected $cache;
    protected $twig;
    protected $config;

    public function __construct(\DB_MYSQL $db, \CACHE $cache, \Twig\Environment $twig, array $config) {
        $this->db = $db;
        $this->cache = $cache;
        $this->twig = $twig;
        $this->config = $config;
    }

    abstract public function run();
}
