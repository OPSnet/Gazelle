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
    private array $authorize = ['GET' => false, 'POST' => true];
    private array $routes = ['GET' => [], 'POST' => []];

    /**
     * Router constructor.
     * @param string $auth_key Authorization key for a user
     */
    public function __construct(
        protected readonly string $auth_key = ''
    ) {}

    public function addRoute(string|array $methods, string $action, string $path, bool $authorize = false): void {
        if (is_array($methods)) {
            foreach ($methods as $method) {
                $this->addRoute($method, $action, $path, $authorize);
            }
        } else {
            if (strtoupper($methods) === 'GET') {
                $this->addGet($action, $path, $authorize);
            } elseif (strtoupper($methods) === 'POST') {
                $this->addPost($action, $path, $authorize);
            }
        }
    }

    public function addGet(string $action, string $file, bool $authorize = false): void {
        $this->routes['GET'][$action] = ['file' => $file, 'authorize' => $authorize];
    }

    public function addPost(string $action, string $file, bool $authorize = true): void {
        $this->routes['POST'][$action] = ['file' => $file, 'authorize' => $authorize];
    }

    public function authorizeGet(bool $authorize = true): void {
        $this->authorize['GET'] = $authorize;
    }

    public function authorizePost(bool $authorize = true): void {
        $this->authorize['POST'] = $authorize;
    }

    public function authorized(): bool {
        return !empty($_REQUEST['auth']) && $_REQUEST['auth'] === $this->auth_key;
    }

    public function hasRoutes(): bool {
        return array_sum(array_map("count", $this->routes)) > 0;
    }

    /**
     * @throws RouterException
     */
    public function getRoute(string $action): string {
        $request_method = strtoupper(empty($_SERVER['REQUEST_METHOD']) ? 'GET' : $_SERVER['REQUEST_METHOD']);
        if (isset($this->routes[$request_method]) && isset($this->routes[$request_method][$action])) {
            $method = $this->routes[$request_method][$action];
        } else {
            throw new RouterException("Invalid action for '{$request_method}' request method");
        }

        if (($this->authorize[$request_method] || $method['authorize']) && !$this->authorized()) {
            throw new InvalidAccessException();
        } else {
            return $method['file'];
        }
    }
}
