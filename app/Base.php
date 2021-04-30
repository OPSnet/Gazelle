<?php

namespace Gazelle;

abstract class Base {
    /** @var \DB_MYSQL */
    protected static $_db;
    protected $db;

    /** @var \CACHE */
    protected static $_cache;
    protected $cache;

    /** @var \Twig\Environment */
    protected static $_twig;
    protected $twig;

    public function __construct() {
        $this->cache =& self::$_cache;
        $this->db    =& self::$_db;
        $this->twig  =& self::$_twig;
    }

    public static function initialize(\CACHE $cache, \DB_MYSQL $db, \Twig\Environment $twig) {
        self::$_db    =& $db;
        self::$_cache =& $cache;
        self::$_twig  =& $twig;
    }
}
