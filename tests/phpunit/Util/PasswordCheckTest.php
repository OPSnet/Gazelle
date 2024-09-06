<?php

namespace Gazelle;

use PHPUnit\Framework\TestCase;
use Gazelle\Util\PasswordCheck;

class PasswordCheckTest extends TestCase {
    protected User $user;

    public function setUp(): void {
        $this->user = \GazelleUnitTest\Helper::makeUser('Pas5wd' . randomString(12), 'passwordStrength');
    }

    public function tearDown(): void {
        $this->user->remove();
    }

    public function testCheckPasswordStrength(): void {
        $this->assertTrue(PasswordCheck::checkPasswordStrength('HJq65UIDeNGT', $this->user), 'passwd-strength-1');
        $this->assertFalse(PasswordCheck::checkPasswordStrength($this->user->username(), $this->user), 'passwd-strength-2');
        $this->assertTrue(PasswordCheck::checkPasswordStrength($this->user->username(), null, false), 'passwd-strength-3');
        $this->assertFalse(PasswordCheck::checkPasswordStrength('password', null, false), 'passwd-strength-4');
    }
}
