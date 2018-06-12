<?php

namespace Gazelle;

class Router {
	private $get = [];
	private $post = [];

	public function addRoute($methods=[], $action, $file) {
		// GET, POST, PUT, DELETE
		if (is_array($methods)) {
			foreach ($methods as $method) {
				$this->addRoute($method, $action, $file);
			}
		}
		else {
			if ($methods === 'GET') {
				$this->addGet($action, $file);
			}
			elseif ($methods === 'POST') {
				$this->addPost($action, $file);
			}
		}
	}

	public function addGet($action, $file) {
		$this->get[$action] = $file;
	}

	public function addPost($action, $file) {
		$this->post[$action] = $file;
	}

	public function getRoute($action) {
		if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($this->get[$action])) {
			return SERVER_ROOT.$this->get[$action];
		}
		elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($this->post[$action])) {
			return SERVER_ROOT.$this->post[$action];
		}
		return false;
	}
}