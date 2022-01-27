<?php

namespace Gazelle;

use Gazelle\Exception\InvalidAccessException;
use Gazelle\Exception\RouterException;

/**
 * Router class to be used by Gazelle to serve up the necessary file on
 * a specified route. Current usage would be that the Router is initialized
 * within gazelle.php and then all sections/<*>/index.php files would
 * set their specific section routes on that global Router object. gazelle.php
 * would then include that index.php for a given section to populate the router,
 * and then call the route from within gazelle.php.
 *
 * By default, we assume that any POST requests will require authorization and any
 * GET request will not.
 */
class Router {
    private $authorize = ['GET' => false, 'POST' => true];
    private $routes = ['GET' => [], 'POST' => []];
    private $auth_key = null;

    /**
     * Router constructor.
     * @param string $auth_key Authorization key for a user
     */
    public function __construct($auth_key = '') {
        $this->auth_key = $auth_key;
    }

    /**
     * @param string|array $methods
     * @param string $action
     * @param string $path
     * @param bool $authorize
     */
    public function addRoute($methods, string $action, string $path, bool $authorize = false) {
        if (is_array($methods)) {
            foreach ($methods as $method) {
                $this->addRoute($method, $action, $path, $authorize);
            }
        }
        else {
            if (strtoupper($methods) === 'GET') {
                $this->addGet($action, $path, $authorize);
            }
            elseif (strtoupper($methods) === 'POST') {
                $this->addPost($action, $path, $authorize);
            }
        }
    }

    public function addGet(string $action, string $file, bool $authorize = false) {
        $this->routes['GET'][$action] = ['file' => $file, 'authorize' => $authorize];
    }

    public function addPost(string $action, string $file, bool $authorize = true) {
        $this->routes['POST'][$action] = ['file' => $file, 'authorize' => $authorize];
    }

    public function authorizeGet(bool $authorize = true) {
        $this->authorize['GET'] = $authorize;
    }

    public function authorizePost(bool $authorize = true) {
        $this->authorize['POST'] = $authorize;
    }

    public function authorized() {
        return !empty($_REQUEST['auth']) && $_REQUEST['auth'] === $this->auth_key;
    }

    public function hasRoutes() {
        return array_sum(array_map("count", $this->routes)) > 0;
    }

    /**
     * @param string $action
     * @return string path to file to load
     * @throws RouterException
     */
    public function getRoute(string $action) {
        $request_method = strtoupper(empty($_SERVER['REQUEST_METHOD']) ? 'GET' : $_SERVER['REQUEST_METHOD']);
        if (isset($this->routes[$request_method]) && isset($this->routes[$request_method][$action])) {
            $method = $this->routes[$request_method][$action];
        }
        else {
            throw new RouterException("Invalid action for '${request_method}' request method");
        }

        if (($this->authorize[$request_method] || $method['authorize']) && !$this->authorized()) {
            throw new InvalidAccessException();
        }
        else {
            return $method['file'];
        }
    }
}
