<?php

namespace Gazelle;

abstract class Base {
    protected static DB\Mysql $db;
    protected static Cache $cache;
    protected static \Twig\Environment $twig;

    public static function initialize(Cache $cache, DB\Mysql $db, \Twig\Environment $twig) {
        self::$db    = $db;
        self::$cache = $cache;
        self::$twig  = $twig;
    }
}
