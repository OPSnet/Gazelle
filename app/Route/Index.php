<?php

declare(strict_types=1);

namespace Gazelle\Route;

use Gazelle\Route;
use Gazelle\RequestContext;
use Gazelle\Cache;
use Gazelle\Debug;

abstract class Index extends Route {
    public static function create(
        RequestContext $ctx,
        Cache $cache,
        Debug $debug,
        \Twig\Environment $twig
    ): Route {
        if ($ctx->hasViewer()) {
            return Index\Authenticated::create($ctx, $cache, $debug, $twig);
        }
        return Index\Unauthenticated::create($ctx, $cache, $debug, $twig);
    }
}
