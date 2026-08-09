<?php

namespace Gazelle;

use PHPUnit\Framework\TestCase;
use GazelleUnitTest\Helper;

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

        $this->assertSame(
            1,
            $user->history()->registerNewEmail($newEmail, true),
            'userhist-record-email'
        );
        $this->assertSame(2, $user->history()->emailTotal(), 'userhist-email-total');
        $list = $user->history()->email(new Search\ASN());
        $this->assertSame($newEmail, $list[0]['email'], 'userhist-list-0');
        $this->assertSame($email,    $list[1]['email'], 'userhist-list-1');
    }

    public function testEmailDuplicate(): void {
        $user = Helper::makeUser('userhist.' . randomString(6), 'userhist');
        $this->userList[] = $user;

        $email = $this->userList[0]->email();
        $user->history()->registerNewEmail($email, true);
        $user->setField('Email', $email)->modify();

        $duplicate = $user->history()->emailDuplicate(new Search\ASN());
        $this->assertCount(1, $duplicate, 'email-duplicate-count');
        $this->assertSame(
            $this->userList[0]->id,
            $duplicate[0]['user_id'],
            'email-duplicate-user-id',
        );
    }

    public function testEmailReset(): void {
        $user  = $this->userList[0];
        $email = 'reset@phpunit';
        $this->assertSame(
            1,
            $user->history()->resetEmail($email, '127.2.3.4'),
            'email-reset-action',
        );
        $this->assertSame(
            $email,
            array_first($user->history()->email(new Search\ASN()))['email'],
            'email-reset-address',
        );
    }

    public function testIpHistory(): void {
        $user  = $this->userList[0];
        $this->assertSame(
            1,
            $user->history()->registerSiteIp('127.10.11.12'),
            'ipaddr-register',
        );
        $this->assertSame(
            2,
            $user->history()->registerSiteIp('127.10.11.12'),
            'ipaddr-reregister',
        );
        $list = $user->history()->siteIP();
        $this->assertCount(1, $list, 'ipaddr-site-history');
        $this->assertSame('127.10.11.12', $list[0]['ip'], 'ipaddr-site-ip');
        $this->assertTrue(
            Helper::recentDateTimeImmutable($list[0]['last_seen']),
            'ipaddr-site-last-seen',
        );
        $this->assertCount(0, $user->history()->apiIP(), 'ipaddr-api-history');
    }
}
