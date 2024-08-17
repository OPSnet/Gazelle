<?php

use PHPUnit\Framework\TestCase;

class UserNavigationTest extends TestCase {
    protected Gazelle\User $user;

    public function setUp(): void {
        $this->user = Helper::makeUser('user.' . randomString(10), 'forum');
    }

    public function tearDown(): void {
        $this->user->remove();
    }

    public function testNavigationBasic(): void {
        $manager = new Gazelle\Manager\UserNavigation();
        $fullList = $manager->fullList();
        $this->assertCount(12, $fullList, 'user-nav-manager-full');

        $this->assertTrue(
            $this->user->setField('nav_list', [$fullList[3]["id"], $fullList[2]["id"], $fullList[1]["id"]])->modify(),
            'user-nav-modify'
        );
        $userList = $manager->userControlList($this->user);
        $this->assertCount(3, $userList, 'user-nav-count');
        $this->assertEquals($fullList[2]["id"], $userList[1]["id"], 'user-nav-order');
    }
}
