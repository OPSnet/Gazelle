<?php

namespace Gazelle;

use PHPUnit\Framework\TestCase;

class RouterTest extends TestCase {
	public function setUp() {
		$_SERVER['REQUEST_METHOD'] = null;
	}

	public function tearDown() {
		$_SERVER['REQUEST_METHOD'] = null;
		unset($_REQUEST['auth']);
	}

	/**
	 * @throws \Gazelle\Exception\RouterException
	 */
	public function testBasic() {
		$router = new Router('auth');
		$router->addGet('action1', 'path');
		$router->addPost('action2', 'path2');
		$_SERVER['REQUEST_METHOD'] = 'GET';
		$this->assertEquals('path', $router->getRoute('action1'));
		$_SERVER['REQUEST_METHOD'] = 'POST';
		$_REQUEST['auth'] = 'auth';
		$this->assertEquals('path2', $router->getRoute('action2'));
		$this->assertTrue($router->hasRoutes());
	}

	/**
	 * @throws Exception\RouterException
	 */
	public function testAddRoutes() {
		$router = new Router();
		$router->authorizePost(false);
		$router->addRoute(['GET', 'POST'], 'action', 'path');
		$_SERVER['REQUEST_METHOD'] = 'GET';
		$this->assertEquals('path', $router->getRoute('action'));
		$_REQUEST['REQUEST_METHOD'] = 'POST';
		$this->assertEquals('path', $router->getRoute('action'));
	}

	/**
	 * @throws Exception\RouterException
	 */
	public function testAuthorizeGet() {
		$router = new Router('auth23');
		$router->addGet('action', 'path', true);
		$_REQUEST['auth'] = 'auth23';
		$this->assertEquals('path', $router->getRoute('action'));
	}

	public function testHasGets() {
		$router = new Router();
		$router->addGet('action', '');
		$this->assertTrue($router->hasRoutes());
	}

	public function testHasPosts() {
		$router = new Router();
		$router->addPost('action', '');
		$this->assertTrue($router->hasRoutes());
	}

	/**
	 * @expectedException \Gazelle\Exception\RouterException
	 * @expectedExceptionMessage Invalid action for set request method
	 */
	public function testInvalidRoute() {
		$router = new Router();
		$_SERVER['REQUEST_METHOD'] = 'GET';
		$router->getRoute('invalid');
	}

	/**
	 * @expectedException \Gazelle\Exception\RouterException
	 * @expectedExceptionMessage You are not authorized to access this action
	 */
	public function testNoAuthGet() {
		$router = new Router();
		$router->authorizeGet();
		$router->addGet('action', 'path');
		$router->getRoute('action');
	}

	/**
	 * @expectedException \Gazelle\Exception\RouterException
	 * @expectedExceptionMessage You are not authorized to access this action
	 */
	public function testNoAuthPost() {
		$router = new Router();
		$router->addPost('action', 'test2');
		$_SERVER['REQUEST_METHOD'] = 'POST';
		$router->getRoute('action');
	}

	/**
	 * @expectedException \Gazelle\Exception\RouterException
	 * @expectedExceptionMessage You are not authorized to access this action
	 */
	public function testInvalidAuth() {
		$router = new Router('auth');
		$router->addPost('action', 'test_path');
		$_SERVER['REQUEST_METHOD'] = 'POST';
		$router->getRoute('action');
	}
}