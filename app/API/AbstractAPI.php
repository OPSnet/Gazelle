<?php

declare(strict_types=1);

namespace Gazelle\API;

abstract class AbstractAPI extends \Gazelle\Base {
    public function __construct(
        protected array $config,
    ) {}

    abstract public function run(): array;
}
