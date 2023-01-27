<?php

use \PHPUnit\Framework\TestCase;

require_once(__DIR__ . '/../../../lib/bootstrap.php');

class RequestManagerTest extends TestCase {
    protected \Gazelle\Manager\Request $manager;

    public function setUp(): void {
        $this->manager = new \Gazelle\Manager\Request;
    }

    public function tearDown(): void {}

    public function testManager() {
        global $DB;
        $requestId = $DB->scalar('SELECT ID from requests');
        if ($requestId) {
            $this->assertInstanceOf('\\Gazelle\\Request', $this->manager->findById($requestId), 'req-man-find-id');
        } else {
            $this->assertTrue(true, 'skipped (no request for request manager)');
        }
    }
}
