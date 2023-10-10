<?php

namespace Gazelle\UserRank;

class Configuration {
    public function __construct(
        protected readonly array $config,
    ) {}

    public function definition(): array {
        return array_keys($this->config);
    }

    public function instance(string $dimension): \Gazelle\UserRank\AbstractUserRank {
        $className = "\\Gazelle\\UserRank\\Dimension\\" . $this->config[$dimension][1];
        return new $className; /** @phpstan-ignore-line */
    }

    public function weight(string $dimension): int {
        return $this->config[$dimension][0];
    }
}
