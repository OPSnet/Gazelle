<?php

namespace Gazelle;

use PHPUnit\Framework\TestCase;
use GazelleUnitTest\Helper;

class IPv4Test extends TestCase {
    use Pg;

    protected array $userList;

    public function setUp(): void {
        $this->userList[] = Helper::makeUser('ipv4.' . randomString(10), 'ipv4man');
    }

    public function tearDown(): void {
        $idList = array_map(fn ($u) => $u->id, $this->userList);
        $placeholders = placeholders($idList);
        $this->pg()->prepared_query("
            delete from ip_ban where id_ip_ban in (
                select id_ip_ban from ip_ban_hist where id_user in ($placeholders))
            ", ...$idList
        );
        DB::DB()->prepared_query("
            DELETE FROM users_history_ips WHERE UserID IN ($placeholders)
            ", ...$idList
        );
        foreach ($this->userList as $user) {
            $user->remove();
        }
        $this->userList = [];
    }

    public function testBanBasic(): void {
        $manager = new Manager\Ban();
        $initial = $manager->total();
        $this->assertFalse($manager->isBanned('127.9.9.55'), 'ipv4-is-not-banned');
        $ban = $manager->create('127.9.9.50', 'phpunit', $this->userList[0]);
        $this->assertGreaterThan(0, $ban->id, 'ipv4-ban-create');
        $this->assertFalse($manager->isBanned('127.9.9.55'), 'ipv4-is-banned');
        $this->assertEquals($initial + 1, $manager->total(), 'ipv4-total');
        $this->assertCount(1, $ban->history(), 'ban-history-initial');
        $ban->addNote('phpunit add note', $this->userList[0]);
        $this->assertEquals(2, $ban->total(), 'ban-total');
        $this->assertTrue(Helper::recentDate($ban->created()), 'ban-created');

        $this->assertFalse($manager->isBanned('127.9.9.61'), 'ipv4-outside-ban-range');
        $manager->create('127.9.9.55/26', 'extend');
        $this->assertTrue($manager->isBanned('127.9.9.61'), 'ipv4-inside-ban-range');

        $manager->setFilterNotes('extend')
            ->setFilterActive(true)
            // need to add a second in the future because Pg offers microsecond resolution
            ->setFilterTime(date('Y-m-d H:i:s', time() - 10), date('Y-m-d H:i:s', time() + 1));
        $list = $manager->page(2, 0);
        $this->assertCount(1, $list, 'ipv4-extend-list');
        $this->assertEquals($ban->id, $list[0]['id'], 'ipv4-extend-id');
        $this->assertEquals(
            "<a href=\"tools.php?action=ip-ban-edit&amp;id={$ban->id}\">IP {$ban->ip()}</a>",
            $ban->link(),
            'ban-link',
        );

        $this->assertEquals(1, $ban->remove(), 'ipv4-ban-remove');
        $this->assertFalse($manager->isBanned('127.9.9.55'), 'ipv4-is-unbanned');
    }

    public function testBanExtend(): void {
        $manager = new Manager\Ban();
        $manager->create('127.100.0.0/24', 'phpunit class 1');
        $manager->create('127.100.1.0/24', 'phpunit class 2');
        // now create a ban that encompasses the above
        $ban = $manager->create('127.100.0.0/22', 'phpunit class 1+2');
        $this->assertInstanceOf(
            Ban::class,
            $manager->findByIp('127.100.0.1'),
            'ip-ban-class-1',
        );
        $this->assertInstanceOf(
            Ban::class,
            $manager->findByIp('127.100.1.1'),
            'ip-ban-class-2',
        );
        $this->assertInstanceOf(
            Ban::class,
            $manager->findByIp('127.100.2.1'),
            'ip-ban-class-3',
        );
        $this->assertEquals('127.100.0.0/22', $ban->ip(), 'ip-ban-ip');
        $this->assertTrue($manager->isBanned('127.100.3.3'), 'ip-is-banned');
        $this->assertTrue($ban->setField('is_active', 'f')->modify());
        $this->assertFalse($ban->isActive(), 'ban-rule-is-inactive');
        $this->assertFalse($manager->isBanned('127.100.3.3'), 'ip-is-unbanned');

        $ban->remove();
    }

    public function testUserPage(): void {
        $ipv4 = new Manager\IPv4();
        $user = $this->userList[0];
        $this->assertEquals(1, $ipv4->register($user, '127.1.0.1'), 'ipv4-create');
        $this->assertEquals(2, $ipv4->userTotal($user), 'ipv4-user-total');
        $this->assertEqualsCanonicalizing(
            ['127.0.0.1', '127.1.0.1'],
            array_map(
                fn ($h) => $h['ip_addr'],
                $ipv4->userPage($user, 3, 0)
            ),
            'ipv4-user-page',
        );
        $manager = new Manager\Ban();
        $this->assertFalse($manager->isBanned('127.1.0.1'), 'ipv4-not-banned');

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
        $this->userList[] = Helper::makeUser('ipv4.' . randomString(10), 'ipv4man');
        $ipv4 = new Manager\IPv4();
        $ip   = '0.1.2.3';
        $ipv4->register($this->userList[0], $ip);
        $ipv4->register($this->userList[1], $ip);
        $ipv4->setFilterIpaddr($ip);

        $db = DB::DB();
        $db->prepared_query("
            UPDATE users_history_ips SET
                StartTime = StartTime - INTERVAL 1 MINUTE
            WHERE UserID = ?
            ", $this->userList[1]->id
        );

        $this->assertEquals(
            [$this->userList[1]->id],
            $ipv4->userOther($this->userList[0]),
            'ipv4-other-success'
        );

        $ipv4->setFilterBefore(90); // before 1m30 ago
        $this->assertEquals(
            [],
            $ipv4->userOther($this->userList[0]),
            'ipv4-other-too-late'
        );

        $ipv4->setFilterBefore(0)->setFilterAfter(30); // after 30s ago
        $this->assertEquals(
            [],
            $ipv4->userOther($this->userList[0]),
            'ipv4-other-too-early'
        );
    }
}
