<?php

namespace Gazelle;

use PHPUnit\Framework\TestCase;

class UserHistoryTest extends TestCase {
    protected array $userList;

    public function setUp(): void {
        $this->userList = [\GazelleUnitTest\Helper::makeUser('userhist.' . randomString(6), 'userhist')];
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
        $history  = new User\History($user);

        $this->assertEquals(
            1,
            $history->registerNewEmail(
                $newEmail,
                true,
                new Manager\IPv4(),
                new Util\Irc(),
                new Util\Mail(),
            ),
            'userhist-record-email'
        );
        $this->assertEquals(2, $history->emailTotal(), 'userhist-email-total');
        $list = $history->email(new Search\ASN());
        $this->assertEquals($newEmail, $list[0]['email'], 'userhist-list-0');
        $this->assertEquals($email,    $list[1]['email'], 'userhist-list-1');
    }

    public function testEmailDuplicate(): void {
        $user = \GazelleUnitTest\Helper::makeUser('userhist.' . randomString(6), 'userhist');
        $this->userList[] = $user;

        $email   = $this->userList[0]->email();
        $history = new User\History($user);
        $history->registerNewEmail(
            $email,
            true,
            new Manager\IPv4(),
            new Util\Irc(),
            new Util\Mail(),
        );
        $user->setField('Email', $email)->modify();

        $duplicate = $history->emailDuplicate(new Search\ASN());
        $this->assertCount(1, $duplicate, 'email-duplicate-count');
        $this->assertEquals($this->userList[0]->id(), $duplicate[0]['user_id'], 'email-duplicate-user-id');
    }

    public function testEmailReset(): void {
        $history = new User\History($this->userList[0]);
        $email   = 'reset@phpunit';
        $this->assertEquals(1, $history->resetEmail($email, '127.2.3.4'), 'email-reset-action');
        $this->assertEquals($email, $history->email(new Search\ASN())[0]['email'], 'email-reset-address');
    }

    public function testIpHistory(): void {
        $history = new User\History($this->userList[0]);
        $this->assertEquals(1, $history->registerSiteIp('127.10.11.12'), 'ipadd-register');
        $this->assertEquals(2, $history->registerSiteIp('127.10.11.12'), 'ipaddr-reregister');
    }
}
