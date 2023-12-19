<?php

use PHPUnit\Framework\TestCase;

require_once(__DIR__ . '/../../../lib/bootstrap.php');
require_once(__DIR__ . '/../../helper.php');

class IPv4Test extends TestCase {
    protected \Gazelle\User $user;

    public function setUp(): void {
        $this->user = Helper::makeUser('ipv4.' . randomString(10), 'ipv4man');
    }

    public function tearDown(): void {
        $this->user->remove();
    }

    public function testBan(): void {
        $ipv4 = new \Gazelle\Manager\IPv4;
        $initial = $ipv4->total();
        // if the following fails, it is due to a previous unittest failure
        $this->assertFalse($ipv4->isBanned('127.9.9.55'), 'ipv4-is-not-banned');
        $id = $ipv4->createBan($this->user->id(), '127.9.9.50', '127.9.9.60', 'phpunit');
        $this->assertGreaterThan(0, $id, 'ipv4-ban-create');
        $this->assertTrue($ipv4->isBanned('127.9.9.55'), 'ipv4-is-banned');
        $this->assertEquals($initial + 1, $ipv4->total(), 'ipv4-total');

        $this->assertFalse($ipv4->isBanned('127.9.9.61'), 'ipv4-outside-ban-range');
        $this->assertEquals(
            1,
            $ipv4->modifyBan($this->user, $id, '127.9.9.50', '127.9.9.61', 'extend'),
            'ipv4-extend'
        );
        $this->assertTrue($ipv4->isBanned('127.9.9.61'), 'ipv4-inside-ban-range');

        $ipv4->setFilterNotes('extend');
        $list = $ipv4->page('id', 'desc', 2, 0);
        $this->assertCount(1, $list, 'ipv4-extend-list');
        $this->assertEquals($id, $list[0]['id'], 'ipv4-extend-id');

        $this->assertEquals(1, $ipv4->removeBan($id), 'ipv4-ban-remove');
        $this->assertFalse($ipv4->isBanned('127.9.9.55'), 'ipv4-is-unbanned');
    }

    public function testUserPage(): void {
        $ipv4 = new \Gazelle\Manager\IPv4;
        $this->assertEquals(1, $ipv4->register($this->user, '127.1.0.1'), 'ipv4-create');
        $this->assertEquals(2, $ipv4->userTotal($this->user), 'ipv4-user-total');
        $this->assertEquals(
            ['127.0.0.1', '127.1.0.1'],
            array_map(
                fn($h) => $h['ip_addr'],
                $ipv4->userPage($this->user, 3, 0)
            ),
            'ipv4-user-page',
        );
        $this->assertFalse($ipv4->isBanned('127.1.0.1'), 'ipv4-not-banned');

        $ipv4->setFilterIpaddrRegexp('^127\.0');
        $this->assertEquals(1, $ipv4->userTotal($this->user), 'ipv4-user-filter-regexp');
        $ipv4->setFilterIpaddrRegexp('^10\.0');
        $this->assertEquals(0, $ipv4->userTotal($this->user), 'ipv4-user-fail-regexp');

        $ipv4->flush();
        $ipv4->setFilterIpaddr('127.1.0.1');
        $this->assertEquals(1, $ipv4->userTotal($this->user), 'ipv4-user-filter-ip');
        $ipv4->setFilterIpaddr('10.1.0.1');
        $this->assertEquals(0, $ipv4->userTotal($this->user), 'ipv4-user-fail-ip');
    }
}
