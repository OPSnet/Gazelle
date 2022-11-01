<?php

namespace Gazelle\API;

abstract class AbstractAPI extends \Gazelle\Base {
    public function __construct(
        protected array $config,
    ) {}

    abstract public function run();
}
