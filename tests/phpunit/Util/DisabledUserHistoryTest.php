<?php

namespace Gazelle;

use Gazelle\Util\DisabledUserHistory;
use PHPUnit\Framework\TestCase;

class DisabledUserHistoryTest extends TestCase {
    public function testHistory(): void {
        $initial = count(DisabledUserHistory::get());
        $user    = new User(666);

        DisabledUserHistory::add($user, "test");
        $userList = DisabledUserHistory::get();
        $this->assertEquals(
            1 + $initial,
            count($userList),
            'disableduserhistory-notempty',
        );

        DisabledUserHistory::add($user, "test2");
        $userList = DisabledUserHistory::get();
        $this->assertEquals(
            2 + $initial,
            count($userList),
            'disableduserhistory-addmore',
        );
        $this->assertEquals("test", $userList[0][1], 'disableduserhistory-reason');
        $this->assertEquals("test2", $userList[1][1], 'disableduserhistory-reason2');
        $this->assertEquals($user->id, $userList[1][0], 'disableduserhistory-userid');
    }
}
