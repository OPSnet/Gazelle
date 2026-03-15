<?php

namespace Gazelle;

use PHPUnit\Framework\TestCase;
use GazelleUnitTest\Helper;
use RobThree\Auth\TwoFactorAuth;

class UserMultiFactorAuthTest extends TestCase {
    use Pg;

    protected User $user;

    public function setUp(): void {
        $this->user = Helper::makeUser('mfa.' . randomString(10), 'mfa');
    }

    public function tearDown(): void {
        new Manager\UserToken()->removeUser($this->user);
        $this->user->remove();
    }

    protected function countTokens(): int {
        return $this->pg()->scalar('
            select count(*)
            from user_token
            where expiry > now()
                and id_user = ?
                and type = ?
            ', $this->user->id, Enum\UserTokenType::mfa->value
        );
    }

    public function testMFA(): void {
        $this->user->toggleAttr('enforce-mfa', true);
        $this->assertTrue($this->user->enforceMFA(), 'user-needs-mfa');

        $mfa     = $this->user->MFA();
        $secret  = $mfa->generateSessionSecret();
        $manager = new Manager\UserToken();

        $this->assertEquals(0, $this->countTokens(), 'utest-no-mfa');
        $recovery = $mfa->create($manager, $secret);
        ['ip' => $ip, 'created' => $created] = $mfa->details();
        $this->assertEquals(
            $this->user->requestContext()->remoteAddr(),
            $ip,
            'utest-mfa-ip',
        );
        $this->assertTrue(Helper::recentDate($created), 'uteset-mfa-created');
        $this->assertIsArray($recovery, 'utest-setup-mfa-array');
        $this->assertCount(10, $recovery, 'utest-setup-mfa-count');
        $this->assertTrue(
            $this->user->auditTrail()->hasEvent(Enum\UserAuditEvent::mfa),
            'utest-mfa-audit'
        );
        $this->assertFalse($this->user->enforceMFA(), 'user-has-mfa');

        $burn = array_pop($recovery);
        $this->assertFalse($mfa->burnRecovery('no such key'), 'utest-no-burn-mfa');
        $this->assertTrue($mfa->burnRecovery($burn), 'utest-burn-mfa');
        Helper::sleepTick(); // wait until the next second
        $this->assertEquals(9, $this->countTokens(), 'utest-less-mfa');
        $this->assertFalse($mfa->burnRecovery($burn), 'utest-burn-twice-mfa');

        $burn = array_pop($recovery);
        $this->assertTrue($mfa->verify($burn), 'utest-burn-verify-mfa');
        $this->assertFalse($mfa->verify('invalid'), 'utest-verify-bad-mfa');
        $this->assertTrue(
            $mfa->verify(new TwoFactorAuth()->getCode($secret)),
            'utest-verify-good-mfa'
        );
        $this->assertTrue($mfa->enabled(), 'utest-has-mfa-key');

        $mfa->requestContext()->setViewer(($this->user));
        $mfa->remove();
        $this->assertEquals(0, $this->countTokens(), 'utest-remove-mfa');
        $mfaList = array_filter(
            $this->user->auditTrail()->fullEventList(),
            fn ($e) => $e['event'] === Enum\UserAuditEvent::mfa->value

        );
        $this->assertCount(4, $mfaList, 'utest-audit-mfa-list');
        $this->assertStringStartsWith('removed', $mfaList[0]['note'], 'utest-audit-mfa-0');
        $this->assertEquals(
            "used recovery token $burn from {$this->user->requestContext()->remoteAddr()}",
            $mfaList[1]['note'],
            'utest-audit-mfa-1'
        );
        $this->assertStringStartsWith('configured', $mfaList[3]['note'], 'utest-audit-mfa-2');
        $this->assertFalse($mfa->enabled(), 'utest-no-mfa-key');
    }

    public function testQrCode(): void {
        $mfa = $this->user->MFA();
        $qrcode = $mfa->generateQrCode(secret: SITE_NAME, logo: QRCODE_LOGO);
        $this->assertInstanceOf(
            \Endroid\QrCode\Writer\Result\ResultInterface::class,
            $qrcode,
            'qrcode-instance-of',
        );
        $this->assertGreaterThan(
            10000,
            strlen($qrcode->getDataUri()),
            'qrcode-data-uri'
        );
    }
}
