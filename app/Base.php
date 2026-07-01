<?php

namespace Gazelle;

abstract class Base {
    use Pg;

    protected static DB\Mysql $db;
    protected static Cache $cache;
    protected static RequestContext $requestContext;
    protected static \Twig\Environment $twig;

    public static function initialize(Cache $cache, DB\Mysql $db, \Twig\Environment $twig): void {
        static::$db    = $db;
        static::$cache = $cache;
        static::$twig  = $twig;
    }

    public static function setRequestContext(RequestContext $c): void {
        static::$requestContext = $c;
    }

    public static function staticRequestContext(): RequestContext {
        return static::$requestContext;
    }

    public function requestContext(): RequestContext {
        return static::$requestContext;
    }

    // *** functions delegated to the request context

    public function logger(): Log {
        return $this->requestContext()->logger();
    }

    public function viewer(): User {
        return $this->requestContext()->viewer();
    }
}
