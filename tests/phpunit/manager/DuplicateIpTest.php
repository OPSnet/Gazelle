<?php

namespace Gazelle;

use PHPUnit\Framework\TestCase;

class DuplicateIpTest extends TestCase {
    protected array $userList;

    public function setUp(): void {
        $ip = '169.254.0.1';
        $this->userList = [
            \GazelleUnitTest\Helper::makeUser('dupip.' . randomString(10), 'duplicate.ip')->setField('IP', $ip)->setField('Enabled', '1'),
            \GazelleUnitTest\Helper::makeUser('dupip.' . randomString(10), 'duplicate.ip')->setField('IP', $ip)->setField('Enabled', '1'),
            \GazelleUnitTest\Helper::makeUser('dupip.' . randomString(10), 'duplicate.ip')->setField('IP', $ip)->setField('Enabled', '1'),
            \GazelleUnitTest\Helper::makeUser('dupip.' . randomString(10), 'duplicate.ip')->setField('IP', $ip)->setField('Enabled', '1'),
            \GazelleUnitTest\Helper::makeUser('dupip.' . randomString(10), 'duplicate.ip')->setField('IP', $ip)->setField('Enabled', '1'),
        ];
        foreach ($this->userList as $user) {
            $user->modify();
        }

        DB::DB()->prepared_query("
            INSERT INTO users_history_ips (UserID, IP, StartTime, EndTime) VALUES
                (?, '$ip', now() - interval 10 day, now() - interval 1 day),
                (?, '$ip', now() - interval 10 day, now() - interval 1 day),
                (?, '$ip', now() - interval 10 day, now() - interval 1 day),
                (?, '$ip', now() - interval 10 day, now() - interval 1 day),
                (?, '$ip', now() - interval 10 day, now() - interval 1 day)
            ", ...array_map(fn($u) => $u->id(), $this->userList)
        );
    }

    public function tearDown(): void {
        $idList = array_map(fn($u) => $u->id(), $this->userList);
        DB::DB()->prepared_query("
            DELETE FROM users_history_ips WHERE UserID IN (" . placeholders($idList) . ")
            ", ...$idList
        );
        foreach ($this->userList as $user) {
            $user->remove();
        }
    }

    public function testDuplicateIpTotal(): void {
        $dup = new Manager\DuplicateIP();
        $total = $dup->total(5);
        $this->assertGreaterThan(1, $total, 'duplicate-ip-total');

        $list = $dup->page(5, 1000, 0);
        $this->assertCount(5, array_filter($list, fn($d) => $d['ipaddr'] == $this->userList[0]->ipaddr()), 'duplicate-ip-page');
    }
}
