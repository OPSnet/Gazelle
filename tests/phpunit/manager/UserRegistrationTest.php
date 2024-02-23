<?php

use PHPUnit\Framework\TestCase;

require_once(__DIR__ . '/../../../lib/bootstrap.php');
require_once(__DIR__ . '/../../helper.php');

class UserRegistrationTest extends TestCase {
    protected array $userList;

    public function setUp(): void {
        $this->userList = [
            Helper::makeUser('reg1.' . randomString(10), 'registration')->setField('created', '1600-01-15 13:01:05'),
            Helper::makeUser('reg2.' . randomString(10), 'registration')->setField('created', '1600-02-15 14:02:10'),
            Helper::makeUser('reg3.' . randomString(10), 'registration')->setField('created', '1600-03-15 15:03:15'),
            Helper::makeUser('reg4.' . randomString(10), 'registration')->setField('created', '2600-01-15 16:04:20'),
            Helper::makeUser('reg5.' . randomString(10), 'registration')->setField('created', '2600-01-15 17:05:25'),
        ];
        foreach ($this->userList as $user) {
            $user->modify();
        }
    }

    public function tearDown(): void {
        foreach ($this->userList as $user) {
            $user->remove();
        }
    }

    public function testUserRegistrationAfter(): void {
        $reg = new Gazelle\Manager\Registration(new Gazelle\Manager\User());
        $reg->setAfterDate("2600-01-01 00:00:01");
        $this->assertEquals(2, $reg->total(), 'user-reg-total-after');

        $list = $reg->page(10, 0);
        $this->assertCount(2, $list, 'user-reg-list-after');
        $this->assertFalse(in_array($this->userList[2], $list), 'user-reg-after-not-2');
        $this->assertTrue(in_array($this->userList[3], $list), 'user-reg-after-3');
    }

    public function testUserRegistrationBefore(): void {
        $reg = new Gazelle\Manager\Registration(new Gazelle\Manager\User());
        $reg->setBeforeDate("1600-12-31");
        $this->assertEquals(3, $reg->total(), 'user-reg-total-before');

        $list = $reg->page(10, 0);
        $this->assertCount(3, $list, 'user-reg-list-before');
        $this->assertTrue(in_array($this->userList[0], $list), 'user-reg-before-0');
        $this->assertTrue(in_array($this->userList[2], $list), 'user-reg-before-2');
        $this->assertFalse(in_array($this->userList[3], $list), 'user-reg-before-not-3');
    }

    public function testUserRegistrationBetween(): void {
        $reg = new Gazelle\Manager\Registration(new Gazelle\Manager\User());
        $reg->setAfterDate("1600-02-01")->setBeforeDate("1600-03-01");
        $this->assertEquals(1, $reg->total(), 'user-reg-total-between');

        $list = $reg->page(10, 0);
        $this->assertCount(1, $list, 'user-reg-list-between');
        $this->assertEquals([$this->userList[1]], $list, 'user-reg-between-1');
    }

    public function testUserRegistrationUnconfirmed(): void {
        $this->assertEquals(3, (new Gazelle\Manager\User())->disableUnconfirmedUsers(), 'user-reg-unconfirmed');
    }
}
