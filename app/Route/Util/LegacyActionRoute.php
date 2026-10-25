<?php

declare(strict_types=1);

namespace Gazelle\Route\Util;

use Gazelle\Cache;
use Gazelle\Debug;
use Gazelle\Error404;
use Gazelle\RequestContext;
use Gazelle\Route;

abstract class LegacyActionRoute extends Route {
    // a missing action will be coerced into an empty action '' == null
    /** @var array<string, string> ACTIONS */
    protected const array ACTIONS = [];  // map ['string' => file beneath sections/]
    protected const string DEFAULT_ACTION = '';

    // @phpstan-ignore parameter.notOptional (child classes must ensure $file is passed)
    protected function __construct(
        public readonly RequestContext    $ctx,
        public readonly Cache             $cache,
        public readonly Debug             $debug,
        public readonly \Twig\Environment $twig,
        public readonly string            $file,
    ) {}

    public function process(): void {
        $Cache  = $this->cache;
        $Debug  = $this->debug;
        $Twig   = $this->twig;
        $Viewer = $this->ctx->hasViewer() ? $this->ctx->viewer() : null;
        require __DIR__ . '/../../../sections/' . $this->file;
    }

    public static function create(
        RequestContext    $ctx,
        Cache             $cache,
        Debug             $debug,
        \Twig\Environment $twig,
    ): Route {
        $action = $_REQUEST['action'] ?? static::DEFAULT_ACTION;
        $file = static::ACTIONS[$action] ?? null;
        if ($file === null) {
            Error404::error('unknown action');
        }
        // @phpstan-ignore new.staticInAbstractClassStaticMethod (this class is for inheritance only)
        $route = new static($ctx, $cache, $debug, $twig, $file);
        $route->action = $action === '' ? null : $action;
        return $route;
    }
}
