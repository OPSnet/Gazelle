<?php

namespace Gazelle;

use PHPUnit\Framework\TestCase;
use GazelleUnitTest\Helper;

class UserCreateTest extends TestCase {
    protected User $user;

    public function tearDown(): void {
        if (isset($this->user)) {
            $this->user->remove();
        }
    }

    public function testCreate(): void {
        $name     = 'create.' . randomString(6);
        $email    = "$name@example.com";
        $password = randomString(40);
        $note     = 'Created by tests/phpunit/UserCreateTest.php';

        $this->user = new UserCreator()
            ->setUsername($name)
            ->setEmail($email)
            ->setPassword($password)
            ->addNote($note)
            ->create();

        $this->assertEquals($name, $this->user->username(), 'user-create-username');
        $this->assertEquals($email, $this->user->email(), 'user-create-email');
        $this->assertStringContainsString(
            $note,
            $this->user->auditTrail()->fullEventList()[0]['note'],
            'user-create-staff-notes'
        );
        $this->assertTrue($this->user->isUnconfirmed(), 'user-create-unconfirmed');
        $this->assertStringContainsString(
            '/static/styles/apollostage/style.css?v=',
            new User\Stylesheet($this->user)->cssUrl(),
            'user-create-stylesheet'
        );

        $location = "user.php?id={$this->user->id}";
        $this->assertEquals($location, $this->user->location(), 'user-location');
        $this->assertEquals(SITE_URL . "/$location", $this->user->publicLocation(), 'user-public-location');
        $this->assertEquals($location, $this->user->url(), 'user-url');
        $this->assertEquals(SITE_URL . "/$location", $this->user->publicUrl(), 'user-public-url');

        $info = $this->user->stats()->info();
        $this->assertCount(25, $info, 'user-empty-stats');
    }

    public function testLogin(): void {
        $this->user = new UserCreator()
            ->setUsername('phpunit.' . randomString(10))
            ->setEmail('email@example.com')
            ->setPassword('password')
            ->addNote('phpunit test login')
            ->create();
        $login = new Login();
        $watch = new LoginWatch($login->requestContext()->remoteAddr());
        $watch->clearAttempts();
        $this->assertEquals(
            $login->requestContext()->remoteAddr(),
            $watch->ipaddr(),
            'user-login-watch-ipaddr'
        );

        $this->assertNull(
            $login->login($this->user->username(), 'not-the-password!', $watch),
            'user-create-login-bad-pw-null'
        );
        $this->assertEquals(
            Enum\LoginStatus::badCredentials,
            $login->error(),
            'user-create-login-bad-pw-error'
        );

        $this->assertNull(
            $login->login($this->user->username(), 'password', $watch),
            'user-create-login-unconfirmed-null'
        );
        $this->assertEquals(
            Enum\LoginStatus::unconfirmed,
            $login->error(),
            'user-create-login-unconfirmed-error',
        );

        $this->assertEquals(2, $watch->nrAttempts(), 'user-create-two-login-attempts');
        $this->assertEquals(60, (int)$watch->bannedEpoch() - time(), 'user-login-ban-epoch');
        $this->assertTrue(Helper::futureDate($watch->bannedUntil(), 70), 'user-login-temporary-ban');
        $this->assertEquals(0, $watch->clearPriorBan(), 'user-login-clear-ban');
        $this->user->setField('Enabled', Enum\UserStatus::enabled->value)->modify();

        $enabledUser = $login->login($this->user->username(), 'password', $watch);
        $this->assertInstanceOf(User::class, $enabledUser, 'user-create-login-success');
        $this->assertEquals(0, $watch->nrAttempts(), 'user-create-two-login-cleared');
        // check the table if this fails
        $this->assertEquals(0, $watch->nrBans(), 'user-create-two-login-banned');
        $this->assertEquals($this->user->username(), $watch->capture(), 'user-login-capture');
        $this->assertGreaterThanOrEqual(0, $watch->activeTotal(), 'user-login-watch-total-sql');

        $relogin = new Login();
        $watch->clearAttempts();
        foreach (range(1, 11) as $nr) {
            $relogin->login($this->user->username(), "multi-fail-$nr", $watch);
        }
        $inbox = $this->user->inbox();
        $this->assertEquals(1, $inbox->messageTotal(), 'user-login-fail-inbox');
        $list    = $inbox->messageList(new Manager\PM($this->user), 3, 0);
        $warning = end($list);
        $this->assertEquals(
            'Too many login attempts on your account',
            $warning->subject(),
            'user-login-pm-subject'
        );
        $this->assertEquals(
            0,
            $watch->setClear([], $this->user),
            'user-login-watch-no-clear',
        );
        $this->assertEquals(
            1,
            $watch->setClear([$watch->id()], $this->user),
            'user-login-watch-clear',
        );
    }

    public function testZeroFailure(): void {
        $_SERVER['HTTP_USER_AGENT'] = 'phpunit';

        $creator = new UserCreator()
            ->setUsername('0')
            ->setEmail("test@example.com")
            ->setPassword(randomString(20))
            ->addNote('Created by tests/phpunit/UserCreateTest.php');

        $this->expectException(Exception\UserCreatorException::class);
        $creator->create();
    }

    public function testNameFailure(): void {
        $_SERVER['HTTP_USER_AGENT'] = 'phpunit';

        $creator = new UserCreator()
            ->setUsername(randomString(21))
            ->setEmail("test@example.com")
            ->setPassword(randomString(20))
            ->addNote('Created by tests/phpunit/UserCreateTest.php');

        $this->expectException(Exception\UserCreatorException::class);
        $creator->create();

        $this->assertFalse($creator->newInstall(), 'user-creator-not-new-install'); // simply to check the SQL
    }

    public function testNameTrim(): void {
        $_SERVER['HTTP_USER_AGENT'] = 'phpunit';
        $this->user = new UserCreator()
            ->setUsername(' ' . randomString(6))
            ->setEmail("test@example.com")
            ->setPassword(randomString(20))
            ->addNote('Created by tests/phpunit/UserCreateTest.php')
            ->create();

        $this->assertInstanceOf(User::class, $this->user, 'user-create-trim');
    }
}
