<?php

namespace Gazelle\API;

abstract class AbstractAPI extends \Gazelle\Base {
    protected $config;

    public function __construct(array $config) {
        parent::__construct();
        $this->config = $config;
    }

    abstract public function run();
}
