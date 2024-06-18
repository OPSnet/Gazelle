<?php

namespace Gazelle;

abstract class Base {
    protected static DB\Mysql $db;
    protected static Cache $cache;
    protected static BaseRequestContext $requestContext;
    protected static \Twig\Environment $twig;

    public static function initialize(Cache $cache, DB\Mysql $db, \Twig\Environment $twig): void {
        static::$db    = $db;
        static::$cache = $cache;
        static::$twig  = $twig;
    }

    public static function setRequestContext(BaseRequestContext $c): void {
        static::$requestContext = $c;
    }

    public function requestContext(): BaseRequestContext {
        return static::$requestContext;
    }

    public function enumList(string $table, string $column): array {
        $columnType = (string)static::$db->scalar("
            SELECT column_type
            FROM information_schema.columns
            WHERE table_schema = ?
                AND table_name = ?
                AND column_name = ?
            ", SQLDB, $table, $column
        );
        if (!preg_match('/^enum\((.*)\)$/', $columnType, $match)) {
            return [];
        }
        return explode(',', str_replace("'", "", $match[1]));
    }

    public function enumDefault(string $table, string $column): ?string {
        $default = static::$db->scalar("
            SELECT column_default
            FROM information_schema.columns
            WHERE table_schema = ?
                AND table_name = ?
                AND column_name = ?
            ", SQLDB, $table, $column
        );
        return $default ? (string)$default : null;
    }
}
