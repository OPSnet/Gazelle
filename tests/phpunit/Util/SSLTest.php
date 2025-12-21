<?php

namespace Gazelle;

use PHPUnit\Framework\TestCase;

class SSLTest extends TestCase {
    public function testAll(): void {
        $host = getenv('TEST_SSL_HOST');
        if ($host) {
            $manager = new Manager\SSLHost();

            $result = $manager->lookup($host, 443);
            $this->assertCount(2, $result, 'ssl-lookup');
            $this->assertTrue(strtotime($result[0]) < date('U'), 'ssl-not-before');
            $this->assertTrue(strtotime($result[1]) > date('U'), 'ssl-not-after');

            $initial = count($manager->list());
            $id = $manager->add($host, 443);
            $this->assertGreaterThan(0, $id, 'ssl-add');
            $this->assertCount($initial + 1, $manager->list(), 'ssl-list');
            $this->assertGreaterThan(0, $manager->expiryDays(), 'ssl-expiry-future');
            $this->assertEquals(1, $manager->removeList([$id]), 'ssl-remove');
        }
    }
}
