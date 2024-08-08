<?php

use PHPUnit\Framework\TestCase;

require_once(__DIR__ . '/../../lib/bootstrap.php');

class UserCreateTest extends TestCase {
    protected Gazelle\User $user;

    public function tearDown(): void {
        if (isset($this->user)) {
            $this->user->remove();
        }
    }

    public function testCreate(): void {
        $name         = 'create.' . randomString(6);
        $email        = "$name@example.com";
        $password     = randomString(40);
        $adminComment = 'Created by tests/phpunit/UserCreateTest.php';

        $this->user = (new \Gazelle\UserCreator())
            ->setUsername($name)
            ->setEmail($email)
            ->setPassword($password)
            ->setAdminComment($adminComment)
            ->create();

        $this->assertEquals($name, $this->user->username(), 'user-create-username');
        $this->assertEquals($email, $this->user->email(), 'user-create-email');
        $this->assertStringContainsString($adminComment, $this->user->staffNotes(), 'user-create-staff-notes');
        $this->assertTrue($this->user->isUnconfirmed(), 'user-create-unconfirmed');
        $this->assertStringStartsWith(
            'static/styles/apollostage/style.css?v=',
            (new Gazelle\User\Stylesheet($this->user))->cssUrl(),
            'user-create-stylesheet'
        );

        $location = "user.php?id={$this->user->id()}";
        $this->assertEquals($location, $this->user->location(), 'user-location');
        $this->assertEquals(SITE_URL . "/$location", $this->user->publicLocation(), 'user-public-location');
        $this->assertEquals($location, $this->user->url(), 'user-url');
        $this->assertEquals(SITE_URL . "/$location", $this->user->publicUrl(), 'user-public-url');

        $info = $this->user->stats()->info();
        $this->assertCount(24, $info, 'user-empty-stats');
    }

    public function testLogin(): void {
        $this->user = (new \Gazelle\UserCreator())
            ->setUsername('phpunit.' . randomString(10))
            ->setEmail('email@example.com')
            ->setPassword('password')
            ->setAdminComment('phpunit test login')
            ->create();
        $login = new Gazelle\Login();
        $watch = new Gazelle\LoginWatch($login->requestContext()->remoteAddr());
        $watch->clearAttempts();

        $result = $login->login($this->user->username(), 'not-the-password!', $watch);
        $this->assertNull($result, 'user-create-login-bad-pw-null');
        $this->assertEquals(\Gazelle\Login::ERR_CREDENTIALS, $login->error(), 'user-create-login-bad-pw-error');

        $result = $login->login($this->user->username(), 'password', $watch);
        $this->assertNull($result, 'user-create-login-unconfirmed-null');
        $this->assertEquals(\Gazelle\Login::ERR_UNCONFIRMED, $login->error(), 'user-create-login-unconfirmed-error');

        $this->assertEquals(2, $watch->nrAttempts(), 'user-create-two-login-attempts');
        $this->user->setField('Enabled', '2')->modify();

        $enabledUser = $login->login($this->user->username(), 'password', $watch);
        $this->assertInstanceOf(\Gazelle\User::class, $enabledUser, 'user-create-login-success');
        $this->assertEquals(0, $watch->nrAttempts(), 'user-create-two-login-cleared');
        $this->assertEquals(0, $watch->nrBans(), 'user-create-two-login-banned');
    }

    public function testZeroFailure(): void {
        $_SERVER['HTTP_USER_AGENT'] = 'phpunit';

        $creator = (new \Gazelle\UserCreator())
            ->setUsername('0')
            ->setEmail("test@example.com")
            ->setPassword(randomString(20))
            ->setAdminComment('Created by tests/phpunit/UserCreateTest.php');

        $this->expectException(Gazelle\Exception\UserCreatorException::class);
        $creator->create();
    }

    public function testNameFailure(): void {
        $_SERVER['HTTP_USER_AGENT'] = 'phpunit';

        $creator = (new \Gazelle\UserCreator())
            ->setUsername(randomString(21))
            ->setEmail("test@example.com")
            ->setPassword(randomString(20))
            ->setAdminComment('Created by tests/phpunit/UserCreateTest.php');

        $this->expectException(Gazelle\Exception\UserCreatorException::class);
        $creator->create();

        $this->assertFalse($creator->newInstall(), 'user-creator-not-new-install'); // simply to check the SQL
    }

    public function testNameTrim(): void {
        $_SERVER['HTTP_USER_AGENT'] = 'phpunit';
        $this->user = (new \Gazelle\UserCreator())
            ->setUsername(' ' . randomString(6))
            ->setEmail("test@example.com")
            ->setPassword(randomString(20))
            ->setAdminComment('Created by tests/phpunit/UserCreateTest.php')
            ->create();

        $this->assertInstanceOf(Gazelle\User::class, $this->user, 'user-create-trim');
    }
}
