<?php

use PHPUnit\Framework\TestCase;

class ApplicantTest extends TestCase {
    protected array $userList;
    protected array $roleList = [];

    public function setUp(): void {
        $this->userList = [
            'admin' => Helper::makeUser('admin.' . randomString(10), 'applicant'),
        ];
        $this->userList['admin']->addCustomPrivilege('admin_manage_applicants');
        $this->userList['admin']->setField('PermissionID', SYSOP)->modify();
    }

    public function tearDown(): void {
        foreach ($this->roleList as $role) {
            $role->remove();
        }
        foreach ($this->userList as $user) {
            \Gazelle\DB::DB()->prepared_query("
                DELETE FROM thread_note WHERE UserID = ?
                ", $user->id()
            );
            $user->remove();
        }
    }

    public function testRoleApply(): void {
        $roleManager = new \Gazelle\Manager\ApplicantRole();
        $this->assertIsArray($roleManager->publishedList(), 'role-manager-list-published-is-array');
        $total = count($roleManager->list());
        $totalPublished = count($roleManager->publishedList());

        $title = 'published-' . randomString(6);
        $published = $this->roleList[]
            = $roleManager->create($title, 'this is a phpunit role', true, $this->userList['admin']);
        $this->assertInstanceOf(Gazelle\ApplicantRole::class, $published, 'applicant-role-instance');
        $this->assertEquals('apply.php?action=view&id=' . $published->id(), $published->location(), 'applicant-role-location');
        $this->assertStringContainsString(html_escape($published->location()), $published->link(), 'applicant-role-link');
        $this->assertIsString($published->created(), 'applicant-role-created');
        $this->assertEquals($published->created(), $published->modified(), 'applicant-role-modified');
        $this->assertEquals($title, $published->title(), 'applicant-role-title');
        $this->assertEquals('this is a phpunit role', $published->description(), 'applicant-role-description');
        $this->assertTrue($published->isPublished(), 'applicant-role-is-published');
        $this->assertEquals($this->userList['admin']->id(), $published->userId(), 'applicant-role-creator');
        $this->assertCount($totalPublished + 1, $roleManager->publishedList(), 'applicant-role-total-published');
        $this->assertCount($total + 1, $roleManager->list(), 'applicant-role-total-all');

        $this->userList['user'] = Helper::makeUser('user.' . randomString(10), 'applicant');
        $manager = new \Gazelle\Manager\Applicant();
        // YUCK
        global $Viewer;
        $Viewer = $this->userList['user'];
        $apply  = $published->apply($this->userList['user'], 'application message');

        $this->assertInstanceOf(Gazelle\Applicant::class, $apply, 'applicant-instance');
        $this->assertInstanceOf(Gazelle\Thread::class, $apply->thread(), 'applicant-thread');
        $this->assertTrue($manager->userIsApplicant($this->userList['user']), 'applicant-user-applied');
        $this->assertEquals('apply.php?action=view&id=' . $apply->id(), $apply->location(), 'applicant-location');
        $this->assertStringContainsString(html_escape($apply->location()), $apply->link(), 'applicant-link');
        $this->assertIsString($apply->created(), 'applicant-role-created');
        $this->assertEquals($this->userList['user']->id(), $apply->userId(), 'applicant-user-id');
        $this->assertEquals($apply->threadId(), $apply->thread()->id(), 'applicant-thread-id');
        $this->assertEquals($apply->role()->title(), $published->title(), 'applicant-title');
        $this->assertEquals('application message', $apply->body(), 'applicant-body');
        $this->assertFalse($apply->isResolved(), 'applicant-is-not-resolved');
        $this->assertTrue($apply->resolve()->isResolved(), 'applicant-is-resolved');
        $this->assertFalse($apply->resolve(false)->isResolved(), 'applicant-is-unresolved');

        $this->assertEquals(1, $published->remove(), 'applicant-role-remove');
    }

    public function testApplicantNote(): void {
        $this->userList['mod'] = Helper::makeUser('mod.' . randomString(10), 'applicant');
        $this->userList['mod']->setField('PermissionID', MOD)->modify();
        $manager = new \Gazelle\Manager\Applicant();
        $new = [
            'admin' => $manager->newReplyTotal($this->userList['admin']),
            'mod'   => $manager->newReplyTotal($this->userList['mod']),
        ];

        $roleManager = new \Gazelle\Manager\ApplicantRole();
        $this->roleList[] = $role =
            $roleManager->create('phpunit ' . randomString(6), 'this is a phpunit role', true, $this->userList['admin']);

        $this->userList['user'] = Helper::makeUser('user.' . randomString(10), 'applicant');
        $apply = $role->apply($this->userList['user'], 'applicant message');
        $this->assertTrue($role->isStaffViewer($this->userList['admin']), 'applicant-note-admin-is-viewer');
        $this->assertFalse($role->isStaffViewer($this->userList['user']), 'applicant-note-user-is-not-viewer');

        $noteId = $apply->saveNote($this->userList['admin'], 'received', 'public');
        $this->assertCount(1, $apply->thread()->story(), 'applicant-note-staff-public');
        $apply->saveNote($this->userList['admin'], 'staff comment', 'staff');
        $this->assertCount(1, $apply->story($this->userList['user']), 'applicant-note-thread-user');
        $this->assertCount(2, $apply->story($this->userList['admin']), 'applicant-note-thread-staff');

        $this->assertEquals(1, $apply->removeNote($noteId), 'applicant-note-remove');
        $this->assertCount(0, $apply->story($this->userList['user']), 'applicant-note-thread-update-user');
        $this->assertCount(1, $apply->story($this->userList['admin']), 'applicant-note-thread-update-staff');

        $apply->saveNote($this->userList['user'], 'follow-up', 'public');
        $this->assertEquals($new['admin'] + 1, $manager->newReplyTotal($this->userList['admin']), 'application-new-reply-admin');
        $this->assertEquals($new['mod'] + 0, $manager->newReplyTotal($this->userList['mod']), 'application-new-reply-mod');
    }

    public function testRoleViewer(): void {
        $this->userList['mod'] = Helper::makeUser('mod.' . randomString(10), 'applicant');
        $this->userList['mod']->setField('PermissionID', MOD)->modify();

        $manager = new \Gazelle\Manager\Applicant();
        $new = [
            'admin' => $manager->newTotal($this->userList['admin']),
            'mod'   => $manager->newTotal($this->userList['mod']),
        ];
        $roleManager = new \Gazelle\Manager\ApplicantRole();
        $this->roleList[] = $basic =
            $roleManager->create('phpunit ' . randomString(6), 'this is a phpunit basic role', true, $this->userList['admin']);
        $basic->setField('viewer_list', '@' . $this->userList['mod']->username());
        $this->assertTrue($basic->modify(), 'applicant-role-add-viewer');
        $this->assertEquals([$this->userList['mod']->id()], $basic->viewerList(), 'applicant-role-viewer-list');

        $this->roleList[] = $admin =
            $roleManager->create('phpunit ' . randomString(6), 'this is a phpunit admin role', true, $this->userList['admin']);

        $this->userList['user'] = Helper::makeUser('user.' . randomString(10), 'applicant');
        global $Viewer;
        $Viewer = $this->userList['user'];
        $applyBasic = $basic->apply($this->userList['user'], 'application message');

        $this->userList['another'] = Helper::makeUser('user.' . randomString(10), 'applicant');
        $this->assertTrue($applyBasic->isViewable($this->userList['admin']), 'application-is-viewable-admin');
        $this->assertTrue($applyBasic->isViewable($this->userList['mod']), 'application-is-viewable-mod');
        $this->assertTrue($applyBasic->isViewable($this->userList['user']), 'application-is-viewable-user');
        $this->assertFalse($applyBasic->isViewable($this->userList['another']), 'application-is-viewable-another');

        $applyAdmin = $admin->apply($this->userList['user'], 'application message');
        $this->assertCount(2, $manager->userList($this->userList['user']), 'application-user-list');
        $this->assertTrue($applyAdmin->isViewable($this->userList['admin']), 'application-admin-is-viewable-admin');
        $this->assertFalse($applyAdmin->isViewable($this->userList['mod']), 'application-admin-is-viewable-mod');
        $this->assertTrue($applyAdmin->isViewable($this->userList['user']), 'application-admin-is-viewable-user');
        $this->assertFalse($applyAdmin->isViewable($this->userList['another']), 'application-admin-is-viewable-another');

        $this->assertEquals($new['admin'] + 2, $manager->newTotal($this->userList['admin']), 'application-new-total-admin');
        $this->assertEquals($new['mod'] + 1, $manager->newTotal($this->userList['mod']), 'application-new-total-mod');
    }

    public function testUnpublishedRole(): void {
        $roleManager = new \Gazelle\Manager\ApplicantRole();
        $total = count($roleManager->list());
        $totalPublished = count($roleManager->publishedList());

        $this->roleList[] = $unpublished =
            $roleManager->create('unpublished-' . randomString(6), 'unpublished phpunit role', false, $this->userList['admin']);
        $this->assertFalse($unpublished->isPublished(), 'applicant-role-is-not-published');
        $this->assertCount($totalPublished + 0, $roleManager->publishedList(), 'applicant-role-total-published');
        $this->assertCount($total + 1, $roleManager->list(), 'applicant-role-total-unpublished');

        $this->userList['user'] = Helper::makeUser('user.' . randomString(10), 'applicant');
        $this->assertFalse($unpublished->isViewable($this->userList['user']), 'applicant-not-published-is-invisible');
        $unpublished->setField('Published', 1)->modify();
        $this->assertTrue($unpublished->isViewable($this->userList['user']), 'applicant-published-is-visible');
    }
}
