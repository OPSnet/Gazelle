<?php

namespace Gazelle\API;

abstract class AbstractAPI extends \Gazelle\Base {
    protected $twig;
    protected $config;

    public function __construct(\Twig\Environment $twig, array $config) {
        parent::__construct();
        $this->twig = $twig;
        $this->config = $config;
    }

    abstract public function run();
}
