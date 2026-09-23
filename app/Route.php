<?php

declare(strict_types=1);

namespace Gazelle;

/** @phpstan-consistent-constructor */
abstract class Route {
    protected ?string $action = null;

    protected function __construct(
        public readonly RequestContext    $ctx,
        public readonly Cache             $cache,
        public readonly Debug             $debug,
        public readonly \Twig\Environment $twig,
    ) {}

    /**
     * Get the route's action parameter, in most cases derived from $_REQUEST.
     * Unless set by the implementing class this will always be null.
     */
    public function action(): ?string {
        return $this->action;
    }

    /**
     * runs page handler and outputs the response data
     */
    abstract public function process(): void;

    /**
     * Factory method. Return a route object. Can be used for class traversal.
     */
    public static function create(
        RequestContext    $ctx,
        Cache             $cache,
        Debug             $debug,
        \Twig\Environment $twig,
    ): Route {
        // @phpstan-ignore new.staticInAbstractClassStaticMethod
        return new static($ctx, $cache, $debug, $twig);
    }
}
