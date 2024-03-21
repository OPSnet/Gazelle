<?php

use PHPUnit\Framework\TestCase;

require_once(__DIR__ . '/../../lib/bootstrap.php');
require_once(__DIR__ . '/../helper.php');

class UserHistoryTest extends TestCase {
    protected array $userList;

    public function setUp(): void {
        $this->userList = [Helper::makeUser('userhist.' . randomString(6), 'userhist')];
    }

    public function tearDown(): void {
        foreach ($this->userList as $user) {
            $user->remove();
        }
    }

    public function testHistoryEmail(): void {
        $user     = $this->userList[0];
        $email    = $user->email();
        $newEmail = "new-$email";
        $history  = new \Gazelle\User\History($user);

        $this->assertEquals(
            1,
            $history->registerNewEmail(
                $newEmail,
                '127.1.2.3',
                true,
                new \Gazelle\Manager\IPv4(),
                new \Gazelle\Util\Irc(),
                new \Gazelle\Util\Mail(),
            ),
            'userhist-record-email'
        );
        $this->assertEquals(2, $history->emailTotal(), 'userhist-email-total');
        $list = $history->email(new \Gazelle\Search\ASN());
        $this->assertEquals($newEmail, $list[0]['email'], 'userhist-list-0');
        $this->assertEquals($email,    $list[1]['email'], 'userhist-list-1');
    }

    public function testEmailDuplicate(): void {
        $user = Helper::makeUser('userhist.' . randomString(6), 'userhist');
        $this->userList[] = $user;

        $email   = $this->userList[0]->email();
        $history = new \Gazelle\User\History($user);
        $history->registerNewEmail(
            $email,
            '127.1.2.3',
            true,
            new \Gazelle\Manager\IPv4(),
            new \Gazelle\Util\Irc(),
            new \Gazelle\Util\Mail(),
        );
        $user->setField('Email', $email)->modify();

        $duplicate = $history->emailDuplicate(new \Gazelle\Search\ASN());
        $this->assertCount(1, $duplicate, 'email-duplicate-count');
        $this->assertEquals($this->userList[0]->id(), $duplicate[0]['user_id'], 'email-duplicate-user-id');
    }

    public function testEmailReset(): void {
        $history = new \Gazelle\User\History($this->userList[0]);
        $email   = 'reset@phpunit';
        $this->assertEquals(1, $history->resetEmail($email, '127.2.3.4'), 'email-reset-action');
        $this->assertEquals($email, $history->email(new \Gazelle\Search\ASN())[0]['email'], 'email-reset-address');
    }
}
