<?php

namespace Gazelle;

use PHPUnit\Framework\TestCase;

class IPv4Test extends TestCase {
    protected array $userList;

    public function setUp(): void {
        $this->userList[] = \GazelleUnitTest\Helper::makeUser('ipv4.' . randomString(10), 'ipv4man');
    }

    public function tearDown(): void {
        foreach ($this->userList as $user) {
            $user->remove();
        }
        $this->userList = [];
    }

    public function testBan(): void {
        $ipv4 = new Manager\IPv4();
        $initial = $ipv4->total();
        // if the following fails, it is due to a previous unittest failure
        $this->assertFalse($ipv4->isBanned('127.9.9.55'), 'ipv4-is-not-banned');
        $id = $ipv4->createBan($this->userList[0], '127.9.9.50', '127.9.9.60', 'phpunit');
        $this->assertGreaterThan(0, $id, 'ipv4-ban-create');
        $this->assertTrue($ipv4->isBanned('127.9.9.55'), 'ipv4-is-banned');
        $this->assertEquals($initial + 1, $ipv4->total(), 'ipv4-total');

        $this->assertFalse($ipv4->isBanned('127.9.9.61'), 'ipv4-outside-ban-range');
        $this->assertEquals(
            1,
            $ipv4->modifyBan($this->userList[0], $id, '127.9.9.50', '127.9.9.61', 'extend'),
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
        $ipv4 = new Manager\IPv4();
        $user = $this->userList[0];
        $this->assertEquals(1, $ipv4->register($user, '127.1.0.1'), 'ipv4-create');
        $this->assertEquals(2, $ipv4->userTotal($user), 'ipv4-user-total');
        $this->assertEquals(
            ['127.0.0.1', '127.1.0.1'],
            array_map(
                fn($h) => $h['ip_addr'],
                $ipv4->userPage($user, 3, 0)
            ),
            'ipv4-user-page',
        );
        $this->assertFalse($ipv4->isBanned('127.1.0.1'), 'ipv4-not-banned');

        $ipv4->setFilterIpaddrRegexp('^127\.0');
        $this->assertEquals(1, $ipv4->userTotal($user), 'ipv4-user-filter-regexp');
        $ipv4->setFilterIpaddrRegexp('^10\.0');
        $this->assertEquals(0, $ipv4->userTotal($user), 'ipv4-user-fail-regexp');

        $ipv4->flush();
        $ipv4->setFilterIpaddr('127.1.0.1');
        $this->assertEquals(1, $ipv4->userTotal($user), 'ipv4-user-filter-ip');
        $ipv4->setFilterIpaddr('10.1.0.1');
        $this->assertEquals(0, $ipv4->userTotal($user), 'ipv4-user-fail-ip');
    }

    public function testUserOther(): void {
        $now = time();
        $ip = '1.2.3.4';
        $user2 = \GazelleUnitTest\Helper::makeUser('ipv4.' . randomString(10), 'ipv4man');
        $this->userList[] = $user2;
        $ipOther = '4.3.2.1';
        $user3 = \GazelleUnitTest\Helper::makeUser('ipv4.' . randomString(10), 'ipv4man');
        $this->userList[] = $user3;
        $ipv4 = new Manager\IPv4();
        $ipv4->register($this->userList[0], $ip);
        $ipv4->register($user2, $ip);
        $ipv4->register($user3, $ipOther);

        $ipv4->setFilterTime($now, time() + 1);
        $ipv4->setFilterIpaddr($ip);
        $this->assertEquals([$user2->id()], $ipv4->userOther($this->userList[0]), 'ipv4-other-success');

        $ipv4->setFilterIpaddr($ipOther);
        $this->assertCount(0, $ipv4->userOther($user3), 'ipv4-other-noresult');
    }
}
