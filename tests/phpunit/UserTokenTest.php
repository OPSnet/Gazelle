<?php

namespace Gazelle;

use PHPUnit\Framework\TestCase;
use Gazelle\Enum\UserAuditEvent;
use Gazelle\Enum\UserTokenType;

class UserTokenTest extends TestCase {
    use Pg;

    protected User $user;

    public function setUp(): void {
        $this->user = \GazelleUnitTest\Helper::makeUser('token.' . randomString(10), 'token');
    }

    public function tearDown(): void {
        (new Manager\UserToken())->removeUser($this->user);
        $this->user->remove();
    }

    public function testUserTokenCreate(): void {
        $manager = new Manager\UserToken();
        $userToken = $manager->create(UserTokenType::password, $this->user);
        $this->assertTrue(\GazelleUnitTest\Helper::recentDate($userToken->expiry()), 'usertoken-expiry');

        $this->assertInstanceOf(User\Token::class, $manager->findById($userToken->id()), 'usertoken-find-by-id');
        $this->assertInstanceOf(User\Token::class, $manager->findByToken($userToken->value()), 'usertoken-find-by-token');
        $this->assertEquals(UserTokenType::password, $userToken->type(), 'usertoken-type');
        $this->assertTrue($userToken->isValid(), 'usertoken-create');

        $this->assertTrue($userToken->consume(), 'usertoken-consume');
        sleep(1);
        $this->assertFalse($userToken->consume(), 'usertoken-already-consumed');

        $this->assertEquals(1, $manager->removeUser($userToken->user()), 'usertoken-remove-user');
    }

    public function testUserTokenPermanent(): void {
        $manager = new Manager\UserToken();
        $userToken = $manager->create(UserTokenType::mfa, $this->user);
        $this->assertEquals('infinity', $userToken->expiry(), 'usertoken-permanent');
    }

    public function testUserTokenExists(): void {
        $manager = new Manager\UserToken();
        $userToken = $manager->create(UserTokenType::confirm, $this->user);
        $this->assertInstanceOf(User\Token::class, $manager->findByUser($userToken->user(), UserTokenType::confirm), 'usertoken-find-by-user');
    }

    public function testPasswordToken(): void {
        $manager = new Manager\UserToken();
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
        $manager = new Manager\UserToken();
        $this->assertNull($manager->findByUser($this->user, UserTokenType::mfa), 'usertoken-missing');
    }

    public function testApiToken(): void {
        $this->assertCount(0, $this->user->apiTokenList(), 'user-token-none-creted');
        $this->assertFalse($this->user->hasApiToken('no such token'), 'user-token-missing');

        $token = $this->user->createApiToken('api-token');
        $this->assertTrue($this->user->hasApiToken($token), 'user-token-create');
        $this->assertTrue($this->user->hasApiTokenByName('api-token'), 'user-has-token-by-name');
        $list = $this->user->apiTokenList();
        $this->assertCount(1, $list, 'user-token-list');
        $this->assertEquals(0, $this->user->revokeApiTokenById(0), 'user-revoke-missing');

        $this->assertCount(0, $this->user->apiTokenList(revoked: true), 'user-token-none-revoked');
        $this->assertEquals(1, $this->user->revokeApiTokenById($list[0]['id']), 'user-revoke-token');
        $this->assertCount(1, $this->user->apiTokenList(revoked: true), 'user-token-one-revoked');
    }
}
