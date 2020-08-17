<?php

namespace Gazelle\UserRank;

class Configuration {
    var $config;

    public function __construct(array $config) {
        $this->config = $config;
    }

    public function definition(): array {
        return array_keys($this->config);
    }

    public function instance(string $dimension): \Gazelle\UserRank\AbstractUserRank {
        $className = "\\Gazelle\\UserRank\\Dimension\\" . $this->config[$dimension][1];
        return new $className;
    }

    public function weight(string $dimension): int {
        return $this->config[$dimension][0];
    }
}
