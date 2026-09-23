<?php

namespace Gazelle\Route\Util;

use Gazelle\Cache;
use Gazelle\Debug;
use Gazelle\Error404;
use Gazelle\RequestContext;
use Gazelle\Route;

abstract class ActionRoute extends Route {
    // a missing action will be coerced into an empty action '' == null
    /** @var array<string, class-string<Route>> ACTIONS */
    protected const array ACTIONS = [];  // map ['string' => Route class]
    protected const string DEFAULT_ACTION = '';

    public static function create(
        RequestContext    $ctx,
        Cache             $cache,
        Debug             $debug,
        \Twig\Environment $twig,
    ): Route {
        $action = $_REQUEST['action'] ?? static::DEFAULT_ACTION;
        $class = static::ACTIONS[$action] ?? null;
        if ($class === null) {
            Error404::error('unknown action');
        }
        $route = new $class($ctx, $cache, $debug, $twig);
        $route->action = $action === '' ? null : $action;
        return $route;
    }
}
