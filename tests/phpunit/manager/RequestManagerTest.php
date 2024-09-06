<?php

namespace Gazelle;

use PHPUnit\Framework\TestCase;

class RequestManagerTest extends TestCase {
    protected Manager\Request $manager;

    public function setUp(): void {
        $this->manager = new Manager\Request();
    }

    public function testManager(): void {
        $requestId = (int)DB::DB()->scalar('SELECT ID from requests');
        if ($requestId) {
            $this->assertInstanceOf(Request::class, $this->manager->findById($requestId), 'req-man-find-id');
        } else {
            $this->assertTrue(true, 'skipped (no request for request manager)');
        }
    }
}
