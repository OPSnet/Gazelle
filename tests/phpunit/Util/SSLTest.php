<?php

namespace Gazelle;

use Gazelle\Util\Curl;
use PHPUnit\Framework\TestCase;

class SSLTest extends TestCase {
    public function testAll(): void {
        $host = 'localhost';
        $port = 8081;

        $curl = new Util\Curl()
            ->setOption(CURLOPT_SSL_VERIFYPEER, false)
            ->setOption(CURLOPT_SSL_VERIFYHOST, 0)
            ->setUseProxy(false);
        $manager = new Manager\SSLHost($curl);

        $afterDate = new \DateTime('2001-02-02T01:01:00Z');

        $result = $manager->lookup($host, $port);
        $this->assertCount(2, $result, 'ssl-lookup');
        $this->assertEquals(new \DateTime('2000-01-01T00:00:00Z'), $result[0], 'ssl-not-before');
        $this->assertEquals($afterDate, $result[1], 'ssl-not-after');

        $initial = count($manager->list());
        $id = $manager->add($host, $port);
        $this->assertGreaterThan(0, $id, 'ssl-add');
        $this->assertSame($initial + 1, $manager->schedule(), 'ssl-schedule');
        $this->assertCount($initial + 1, $manager->list(), 'ssl-list');
	$this->assertLessThan(0, $manager->expiryDays(), 'ssl-expiry-past-neg');
        $this->assertSame($afterDate->diff(new \DateTime())->days, -$manager->expiryDays(), 'ssl-expiry-past');
        $this->assertSame(1, $manager->removeList([$id]), 'ssl-remove');
    }
}
