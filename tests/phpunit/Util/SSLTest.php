<?php

namespace Gazelle;

use \PHPUnit\Framework\TestCase;

class SSLTest extends TestCase {

    protected Manager\SSLHost $manager;

    public function setUp(): void {
        $this->manager = new Manager\SSLHost;
    }

    public function tearDown(): void {
    }

    public function testAll() {
        $host = getenv('TEST_SSL_HOST');
        if ($host !== false) {
            $result = $this->manager->lookup($host, 443);
            $this->assertCount(2, $result, 'ssl-lookup');
            $this->assertTrue(strtotime($result[0]) < date('U'), 'ssl-not-before');
            $this->assertTrue(strtotime($result[1]) > date('U'), 'ssl-not-after');

            $id = $this->manager->add($host, 443);
            $this->assertGreaterThan(0, $id, 'ssl-add');
            $this->assertCount(1, $this->manager->list(), 'ssl-list');
            $this->assertTrue($this->manager->expirySoon('1 YEAR'), 'ssl-expiry-future');
            $this->assertFalse($this->manager->expirySoon('-1 YEAR'), 'ssl-expiry-past');
            $this->assertEquals(1, $this->manager->removeList([$id]), 'ssl-remove');
        }
    }
}
