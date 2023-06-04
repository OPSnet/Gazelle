<?php

use \PHPUnit\Framework\TestCase;

require_once(__DIR__ . '/../../lib/bootstrap.php');
require_once(__DIR__ . '/../helper.php');

class ApplicantTest extends TestCase {
    public function testApplicant(): void {
        $roleManager = new \Gazelle\Manager\ApplicantRole;
        $manager     = new \Gazelle\Manager\Applicant;

        $this->assertIsArray($roleManager->list(), 'role-manager-list-published-is-array');
        $new = $manager->newApplicantCount();
        $admin = Helper::makeUser('admin.' . randomString(10), 'applicant');
        $user  = Helper::makeUser('user.' . randomString(10), 'applicant');
        $admin->setField('PermissionID', SYSOP)->modify();

        $role = 'published-' . randomString(6);
        $published = $roleManager->create($role, 'this is a published role', true, 1);
        $this->assertInstanceOf(Gazelle\ApplicantRole::class, $published, 'applicant-role-instance');

        $unpublished = $roleManager->create('unpublished-' . randomString(6), 'this is an unpublished role', false, $admin->id());
        $this->assertEquals($role, $roleManager->title($published->id()), 'role-manager-title');

        $apply = $manager->create($user->id(), $published->id(), 'application message');
        $this->assertInstanceOf(Gazelle\Applicant::class, $apply, 'applicant-instance');
        $this->assertTrue($manager->userIsApplicant($user->id()), 'applicant-user-applied');
        $this->assertEquals($new + 1, $manager->newApplicantCount(), 'applicant-new-count');

        $this->assertEquals(1, $published->remove(), 'role-published-remove');
        $this->assertEquals(1, $unpublished->remove(), 'role-unpublished-remove');

        $admin->remove();
        $user->remove();
    }
}
