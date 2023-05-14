<?php

use \PHPUnit\Framework\TestCase;

require_once(__DIR__ . '/../../lib/bootstrap.php');
require_once(__DIR__ . '/../helper.php');

use \Gazelle\Enum\UserTokenType;

class UserTokenTest extends TestCase {
    use \Gazelle\Pg;

    protected \Gazelle\User $user;

    public function setUp(): void {
        $this->user = Helper::makeUser('user.' . randomString(6), 'user');
    }

    public function tearDown(): void {
        (new \Gazelle\Manager\UserToken)->removeUser($this->user);
        $this->user->remove();
    }

    public function testUserTokenCreate(): void {
        $manager = new \Gazelle\Manager\UserToken;
        $userToken = $manager->create(UserTokenType::password, $this->user);
        $this->assertStringStartsWith(date('Y-m-d '), $userToken->expiry(), 'usertoken-expiry');

        $this->assertInstanceOf(\Gazelle\User\Token::class, $manager->findById($userToken->id()), 'usertoken-find-by-id');
        $this->assertInstanceOf(\Gazelle\User\Token::class, $manager->findByToken($userToken->value()), 'usertoken-find-by-token');
        $this->assertEquals(UserTokenType::password, $userToken->type(), 'usertoken-type');
        $this->assertTrue($userToken->isValid(), 'usertoken-create');

        $this->assertTrue($userToken->consume(), 'usertoken-consume');
        sleep(1);
        $this->assertFalse($userToken->consume(), 'usertoken-already-consumed');

        $this->assertEquals(1, $manager->removeUser($userToken->user()), 'usertoken-remove-user');
    }

    public function testUserTokenPermanent(): void {
        $manager = new \Gazelle\Manager\UserToken;
        $userToken = $manager->create(UserTokenType::mfa, $this->user);
        $this->assertEquals('infinity', $userToken->expiry(), 'usertoken-permanent');
    }

    public function testUserTokenExists(): void {
        $manager = new \Gazelle\Manager\UserToken;
        $userToken = $manager->create(UserTokenType::confirm, $this->user);
        $this->assertInstanceOf(\Gazelle\User\Token::class, $manager->findByUser($userToken->user(), UserTokenType::confirm), 'usertoken-find-by-user');
    }

    public function testPasswordToken(): void {
        $manager = new \Gazelle\Manager\UserToken;
        $userToken = $manager->createPasswordResetToken($this->user);
        $this->assertEquals('1 day', UserTokenType::confirm->interval(), 'usertoken-confirm-interval');

        $this->assertEquals(
            1,
            $this->pg()->prepared_query("
                update user_token set
                    expiry = now() - '1 second'::interval
                where id_user_token = ?
                ", $userToken->id()
            ),
            'usertoken-pg-update'
        );
        $this->assertEquals(1, $manager->expireTokens(UserTokenType::password), 'usertoken-expire');
    }

    public function testUserTokenMissing(): void {
        $manager = new \Gazelle\Manager\UserToken;
        $this->assertNull($manager->findByUser($this->user, UserTokenType::mfa), 'usertoken-missing');
    }

    public function testMFA(): void {
        $manager = new \Gazelle\Manager\UserToken;
        $this->assertCount(0, $this->user->list2FA(), 'utest-no-mfa');
        $this->assertEquals(1, $this->user->create2FA($manager, randomString(16)), 'utest-setup-mfa');
        $recovery = $this->user->list2FA();
        $this->assertCount(10, $recovery, 'utest-list-mfa');
    }
}
