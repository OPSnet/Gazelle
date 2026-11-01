<?php

namespace Gazelle;

use PHPUnit\Framework\TestCase;
use GazelleUnitTest\Helper;

class SearchEmailTest extends TestCase {
    protected array $userList;

    public function setUp(): void {
        $this->userList = [
            Helper::makeUser('email1.' . randomString(10), 'email-search'),
            Helper::makeUser('email2.' . randomString(10), 'email-search'),
            Helper::makeUser('email3.' . randomString(10), 'email-search'),
        ];
    }

    public function tearDown(): void {
        foreach ($this->userList as $user) {
            $user->remove();
        }
    }

    public function testSearchEmail(): void {
        $search = new Search\Email();
        $this->assertSame(
            [],
            $search->extract('no emails here'),
            'search-email-no-extract',
        );

        $text = "chaff {$this->userList[0]->email()} chaff {$this->userList[1]->email()} chaff {$this->userList[2]->email()} dup {$this->userList[0]->email()} ";
        $list = $search->extract($text);
        $this->assertCount(3, $list, 'search-email-extract');
        $this->assertSame(0, $search->add([]), 'search-add-empty');
        $this->assertSame(3, $search->add($list), 'search-add-list');

        $emailList = array_map(fn($u) => $u->email(), $this->userList);
        sort($emailList, SORT_STRING | SORT_FLAG_CASE);
        $this->assertSame($emailList, $search->emailList(), 'search-email-list');

        $this->assertSame(
            3,
            $search->liveTotal(),
            'search-email-live-total'
        );
        $search->setColumn(Search\Email::USER);
        $search->setDirection(Search\Email::DESC);
        $liveList = $search->liveList(3, 0);
        $this->assertCount(3, $liveList, 'search-live-list-count');
        $nameList = array_map(fn($u) => $u->username(), $this->userList);
        rsort($nameList);
        $this->assertSame(
            $nameList,
            array_map(fn($e) => $e['username'], $liveList),
            'search-live-page-order',
        );
    }

    public function testSearchHistoryEmail(): void {
        $newEmail = 'email1.' . randomString(11) . '+abc@example.com';
        $this->userList[0]->history()->registerNewEmail($newEmail, false);

        $search = new Search\Email();
        $list = $search->extract("here is my email $newEmail");
        $this->assertSame(1, $search->add($list), 'search-hist-add-list');
        $this->assertSame(1, $search->historyTotal(), 'search-hist-total');
        $this->assertCount(1, $search->historyList(2, 0), 'search-hist-list');
    }
}
