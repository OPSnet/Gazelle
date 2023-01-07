<?php

namespace Gazelle;

use \PHPUnit\Framework\TestCase;

require_once(__DIR__ . '/../../lib/bootstrap.php');

class UserCreateTest extends TestCase {

    protected UserCreator $userCreator;

    public function setUp(): void {
        $_SERVER['HTTP_USER_AGENT'] = 'phpunit';
        $this->userCreator          = new UserCreator;
    }

    public function tearDown(): void {}

    public function testCreate() {
        $name         = 'create.' . randomString(6);
        $email        = "$name@example.com";
        $password     = randomString(40);
        $adminComment = 'Created by tests/phpunit/UserCreateTest.php';
        $user = $this->userCreator
            ->setUsername($name)
            ->setEmail($email)
            ->setPassword($password)
            ->setIpaddr('127.0.0.1')
            ->setAdminComment($adminComment)
            ->create();

        $this->assertEquals($name, $user->username(), 'user-create-username');
        $this->assertEquals($email, $user->email(), 'user-create-email');
        $this->assertStringContainsString($adminComment, $user->staffNotes(), 'user-create-staff-notes');
    }
}
