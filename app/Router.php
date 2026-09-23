<?php

declare(strict_types=1);

namespace Gazelle;

/**
 * factory class for routes
 */
readonly class Router {
    public static function resolve(
        RequestContext    $ctx,
        Cache             $cache,
        Debug             $debug,
        \Twig\Environment $twig,
    ): ?Route {
        if (!preg_match('/^[a-z0-9]+(_[a-z0-9]+)*$/', $ctx->module())) {
            return null;
        }
        // convert snake_case to PascalCase
        $moduleClassName = str_replace('_', '', ucwords($ctx->module(), '_'));
        /** @phpstan-var class-string<Route> $class */
        $class = "\\Gazelle\\Route\\{$moduleClassName}";
        if (class_exists($class)) {
            return $class::create($ctx, $cache, $debug, $twig);
        }
        if (LegacyRoute::resolveFile($ctx) === false) {
            return null;
        }
        return LegacyRoute::create($ctx, $cache, $debug, $twig);
    }
}
