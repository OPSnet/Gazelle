<?php

namespace Gazelle;

use PHPUnit\Framework\TestCase;

class RouterTest extends TestCase {
    public function setUp(): void {
        $_SERVER['REQUEST_METHOD'] = null;
    }

    public function tearDown(): void {
        $_SERVER['REQUEST_METHOD'] = null;
        unset($_REQUEST['auth']);
    }

    public function testBasic() {
        $router = new Router('auth');
        $this->assertFalse($router->hasRoutes());
        $router->addGet('action1', 'path');
        $this->assertTrue($router->hasRoutes());
        $router->addPost('action2', 'path2');
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $this->assertEquals('path', $router->getRoute('action1'));
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_REQUEST['auth'] = 'auth';
        $this->assertEquals('path2', $router->getRoute('action2'));
        $this->assertTrue($router->hasRoutes());
    }

    public function testAddRoutes() {
        $router = new Router();
        $router->authorizePost(false);
        $router->addRoute(['GET', 'POST'], 'action', 'path');
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $this->assertEquals('path', $router->getRoute('action'));
        $_REQUEST['REQUEST_METHOD'] = 'POST';
        $this->assertEquals('path', $router->getRoute('action'));
    }

    public function testAuthorizeGet() {
        $router = new Router('auth23');
        $router->addGet('action', 'path', true);
        $_REQUEST['auth'] = 'auth23';
        $this->assertEquals('path', $router->getRoute('action'));
    }

    public function testHasGets() {
        $router = new Router();
        $this->assertFalse($router->hasRoutes());
        $router->addGet('action', '');
        $this->assertTrue($router->hasRoutes());
    }

    public function testHasPosts() {
        $router = new Router();
        $this->assertFalse($router->hasRoutes());
        $router->addPost('action', '');
        $this->assertTrue($router->hasRoutes());
    }

    public function testInvalidRoute() {
        $router = new Router();
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $this->expectException(\Gazelle\Exception\RouterException::class);
        $this->expectExceptionMessage("Invalid action for 'GET' request method");
        $router->getRoute('invalid');
    }

    public function testNoAuthGet() {
        $router = new Router();
        $router->authorizeGet();
        $router->addGet('action', 'path');
        $this->expectException(\Gazelle\Exception\InvalidAccessException::class);
        $this->expectExceptionMessage('You are not authorized to access this action');
        $router->getRoute('action');
    }

    public function testNoAuthPost() {
        $router = new Router();
        $router->addPost('action', 'test2');
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $this->expectException(\Gazelle\Exception\InvalidAccessException::class);
        $this->expectExceptionMessage('You are not authorized to access this action');
        $router->getRoute('action');
    }

    public function testInvalidAuth() {
        $router = new Router('auth');
        $router->addPost('action', 'test_path');
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $this->expectException(\Gazelle\Exception\InvalidAccessException::class);
        $this->expectExceptionMessage('You are not authorized to access this action');
        $router->getRoute('action');
    }
}
