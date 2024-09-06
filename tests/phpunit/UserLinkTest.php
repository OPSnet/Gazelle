<?php

namespace Gazelle;

use PHPUnit\Framework\TestCase;

class UserLinkTest extends TestCase {
    protected array $userList = [];

    public function tearDown(): void {
        foreach ($this->userList as $user) {
            $user->remove();
        }
    }

    public function testUserLinkBasic(): void {
        $this->userList[] = \GazelleUnitTest\Helper::makeUser('ul1.' . randomString(10), 'userlink');
        $linker = new User\UserLink($this->userList[0]);

        $this->assertInstanceOf(User\UserLink::class, $linker->flush(), 'user-link-flush');
        $this->assertEquals($this->userList[0]->link(), $linker->link(), 'user-link-html-link');
        $this->assertEquals($this->userList[0]->location(), $linker->location(), 'user-link-html-location');
    }

    public function testUserLinkInfo(): void {
        $this->userList = [
            \GazelleUnitTest\Helper::makeUser('ul1.' . randomString(10), 'userlink'),
            \GazelleUnitTest\Helper::makeUser('ul2.' . randomString(10), 'userlink'),
        ];
        $linker = new User\UserLink($this->userList[0]);

        $this->assertTrue(
            $linker->dupe($this->userList[1], $this->userList[0], true),
            'user-link-add'
        );
        $this->assertStringContainsString(
            "Linked accounts updated: [user]{$this->userList[0]->username()}[/user] and [user]{$this->userList[1]->username()}[/user]",
            $this->userList[1]->flush()->staffNotes(),
            'user-link-staff-note'
        );
        $this->assertEquals(
            $linker->groupId($this->userList[0]),
            $linker->groupId($this->userList[1]),
            'user-link-same',
        );
        $this->assertFalse(
            $linker->dupe($this->userList[1], $this->userList[0], true),
            'user-link-already-added'
        );

        $comment = randomString(12);
        $this->assertTrue(
            $linker->addGroupComment($comment, $this->userList[0], true),
            'user-link-comment-add'
        );
        $this->assertFalse(
            $linker->addGroupComment($comment, $this->userList[0], true),
            'user-link-no-op-comment-add'
        );
        $info = $linker->info();
        $this->assertEquals($linker->groupId($this->userList[0]), $info['id'], 'user-link-info-id');
        $this->assertEquals($comment, $info['comment'], 'user-link-info-comment');
        $this->assertCount(1, $info['list'], 'user-link-info-list');
        $this->assertEquals(
            [$this->userList[1]->id() => $this->userList[1]->username()],
            $info['list'],
            'user-link-info-user'
        );
        $this->assertEquals(
            [$this->userList[0]->id() => $this->userList[0]->username()],
            (new User\UserLink($this->userList[1]))->info()['list'],
            'user-link-info-other-user'
        );
    }

    public function testUserLinkLifeCycle(): void {
        $this->userList = [
            \GazelleUnitTest\Helper::makeUser('ul1.' . randomString(10), 'userlink'),
            \GazelleUnitTest\Helper::makeUser('ul2.' . randomString(10), 'userlink'),
            \GazelleUnitTest\Helper::makeUser('ul3.' . randomString(10), 'userlink'),
        ];
        $linker = new User\UserLink($this->userList[0]);
        $linker->dupe($this->userList[1], $this->userList[0], true);

        $groupId = $linker->groupId($this->userList[0]);
        $linker->dupe($this->userList[2], $this->userList[0], true);
        $this->assertEquals(
            $linker->groupId($this->userList[1]),
            $linker->groupId($this->userList[2]),
            'user-link-linked-same',
        );

        $this->assertCount(2, $linker->info()['list'], 'user-link-two');
        $this->assertEquals(1, $linker->removeUser($this->userList[1], $this->userList[0]), 'user-link-remove-group');
        $this->assertEquals(1, $linker->removeUser($this->userList[2], $this->userList[0]), 'user-link-remove-last');
    }

    public function testUserLinkMergeGroup(): void {
        $this->userList = [
            \GazelleUnitTest\Helper::makeUser('ul0.' . randomString(10), 'userlink'), // the admin
            \GazelleUnitTest\Helper::makeUser('ul1.' . randomString(10), 'userlink'),
            \GazelleUnitTest\Helper::makeUser('ul2.' . randomString(10), 'userlink'),
            \GazelleUnitTest\Helper::makeUser('ul3.' . randomString(10), 'userlink'),
            \GazelleUnitTest\Helper::makeUser('ul4.' . randomString(10), 'userlink'),
        ];

        // link 1 and 2
        $linka = new User\UserLink($this->userList[1]);
        $linka->dupe($this->userList[2], $this->userList[0], true);

        // link 3 and 4
        $linkb = new User\UserLink($this->userList[3]);
        $linkb->dupe($this->userList[4], $this->userList[0], true);
        $linkb->dupe($this->userList[2], $this->userList[0], true);

        // now link 1 and 3, which means 1-2 and 3-4 are all linked together
        $linka->dupe($this->userList[3], $this->userList[0], true);

        // 2 and 3 should have the same group
        $this->assertEquals(
            $linka->groupId($this->userList[2]),
            $linkb->groupId($this->userList[3]),
            'user-link-merge-group',
        );
        $this->assertEquals(
            [
                $this->userList[2]->id() => $this->userList[2]->username(),
                $this->userList[3]->id() => $this->userList[3]->username(),
                $this->userList[4]->id() => $this->userList[4]->username(),
            ],
            $linka->info()['list'],
            'user-link-merged-1'
        );
        $this->assertEquals(
            [
                $this->userList[1]->id() => $this->userList[1]->username(),
                $this->userList[2]->id() => $this->userList[2]->username(),
                $this->userList[3]->id() => $this->userList[3]->username(),
            ],
            (new User\UserLink($this->userList[4]))->info()['list'],
            'user-link-merged-4'
        );
    }
}
