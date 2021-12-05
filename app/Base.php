<?php

namespace Gazelle;

abstract class Base {
    protected static \DB_MYSQL $db;
    protected static \Gazelle\Cache $cache;
    protected static \Twig\Environment $twig;

    public static function initialize(\Gazelle\Cache $cache, \DB_MYSQL $db, \Twig\Environment $twig) {
        self::$db    = $db;
        self::$cache = $cache;
        self::$twig  = $twig;
    }
}
