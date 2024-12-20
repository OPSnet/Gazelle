<?php

use PHPUnit\Framework\TestCase;

class RequestManagerTest extends TestCase {
    protected \Gazelle\Manager\Request $manager;

    public function setUp(): void {
        $this->manager = new \Gazelle\Manager\Request();
    }

    public function testManager(): void {
        $requestId = (int)Gazelle\DB::DB()->scalar('SELECT ID from requests');
        if ($requestId) {
            $this->assertInstanceOf('\\Gazelle\\Request', $this->manager->findById($requestId), 'req-man-find-id');
        } else {
            $this->assertTrue(true, 'skipped (no request for request manager)');
        }
    }
}
