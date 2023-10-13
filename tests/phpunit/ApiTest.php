<?php

use PHPUnit\Framework\TestCase;

require_once(__DIR__ . '/../../lib/bootstrap.php');

class ApiTest extends TestCase {
    public function testUserId(): void {
        $api = new Gazelle\API\User([]);

        $_GET['user_id'] = 1;
        $_GET['req']     = 'stats';
        $this->assertArrayHasKey('Username', $api->run());
    }

    public function testUsername(): void {
        $api = new Gazelle\API\User([]);

        $_GET['username'] = 'admin';
        $_GET['req']      = 'stats';
        $this->assertArrayHasKey('Username', $api->run());
    }
}
