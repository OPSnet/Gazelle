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
        $this->assertCount(
            min(DisabledUserHistory::MAX_ENTRIES, 1 + $initial),
            $userList,
            'disableduserhistory-notempty',
        );

        DisabledUserHistory::add($user, "test2");
        $userList = DisabledUserHistory::get();
        $this->assertCount(
            min(DisabledUserHistory::MAX_ENTRIES, 2 + $initial),
            $userList,
            'disableduserhistory-addmore',
        );
        $this->assertSame("test", $userList[0][1], 'disableduserhistory-reason');
        $this->assertSame("test2", $userList[1][1], 'disableduserhistory-reason2');
        $this->assertSame($user->id, $userList[1][0], 'disableduserhistory-userid');
    }
}
