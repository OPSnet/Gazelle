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
        $search = new Search\Email(new Search\ASN());
        $search->create(randomString());

        $text = "chaff {$this->userList[0]->email()} chaff {$this->userList[1]->email()} chaff {$this->userList[2]->email()} dup {$this->userList[0]->email()} ";
        $list = $search->extract($text);
        $this->assertCount(3, $list, 'search-email-extract');
        $this->assertSame(0, $search->add([]), 'search-add-empty');
        $this->assertSame(3, $search->add($list), 'search-add-list');

        $emailList = array_map(fn($u) => $u->email(), $this->userList);
        sort($emailList);
        $this->assertSame($emailList, $search->emailList(), 'search-email-list');

        $search->setColumn(1);    // Username
        $search->setDirection(1); // DESC
        $liveList = $search->liveList(3, 0);
        $this->assertCount(3, $liveList, 'search-live-list-count');
        $nameList = array_map(fn($u) => $u->username(), $this->userList);
        rsort($nameList);
        $this->assertSame($nameList, array_map(fn($e) => $e['username'], $liveList), 'searach-live-page-order');
    }
}
