<?php

namespace Gazelle;

use Gazelle\Enum\UserTokenType;
use Gazelle\Enum\UserAuditEvent;
use GazelleUnitTest\Helper;
use PHPUnit\Framework\TestCase;

class UserMultiFactorAuthTest extends TestCase {
    use Pg;

    protected User $user;

    public function setUp(): void {
        $this->user = Helper::makeUser('mfa.' . randomString(10), 'mfa');
    }

    public function tearDown(): void {
        (new Manager\UserToken())->removeUser($this->user);
        $this->user->remove();
    }

    protected function countTokens(): int {
        return $this->pg()->scalar('
            select count(*) from user_token
            where id_user = ?
              and type = ?
              and expiry > now()
            ', $this->user->id(), UserTokenType::mfa->value
        );
    }

    public function testMFA(): void {
        $auth = new \RobThree\Auth\TwoFactorAuth();
        $secret = $auth->createSecret();
        $mfa = $this->user->MFA();
        $manager = new Manager\UserToken();

        $this->assertEquals(0, $this->countTokens(), 'utest-no-mfa');
        $recovery = $mfa->create($manager, $secret);
        $this->assertCount(10, $recovery, 'utest-setup-mfa');
        $this->assertTrue($this->user->auditTrail()->hasEvent(UserAuditEvent::mfa), 'utest-mfa-audit');

        $burn = array_pop($recovery);
        $this->assertFalse($mfa->burnRecovery('no such key'), 'utest-no-burn-mfa');
        $this->assertTrue($mfa->burnRecovery($burn), 'utest-burn-mfa');
        sleep(1); // pg table's time resolution is too low and causes race
        $this->assertEquals(9, $this->countTokens(), 'utest-less-mfa');
        $this->assertFalse($mfa->burnRecovery($burn), 'utest-burn-twice-mfa');

        $burn = array_pop($recovery);
        $this->assertTrue($mfa->verify($burn), 'utest-burn-verify-mfa');
        $this->assertFalse($mfa->verify('invalid'), 'utest-verify-bad-mfa');
        $this->assertTrue($mfa->verify($auth->getCode($secret)), 'utest-verify-good-mfa');
        $this->assertTrue($mfa->enabled(), 'utest-has-mfa-key');

        $mfa->remove();
        $this->assertEquals(0, $this->countTokens(), 'utest-remove-mfa');
        $mfaList = array_filter(
            $this->user->auditTrail()->eventList(),
            fn ($e) => $e['event'] === UserAuditEvent::mfa->value

        );
        $this->assertCount(4, $mfaList, 'utest-audit-mfa-list');
        $this->assertStringStartsWith('removed', $mfaList[0]['note'], 'utest-audit-mfa-0');
        $this->assertEquals("used recovery token $burn", $mfaList[1]['note'], 'utest-audit-mfa-1');
        $this->assertStringStartsWith('configured', $mfaList[3]['note'], 'utest-audit-mfa-2');
        $this->assertFalse($mfa->enabled(), 'utest-no-mfa-key');
    }
}
