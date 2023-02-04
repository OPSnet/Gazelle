<?php

use \PHPUnit\Framework\TestCase;

require_once(__DIR__ . '/../../lib/bootstrap.php');

class applicantTest extends TestCase {
    protected \Gazelle\Manager\ApplicantRole $roleManager;
    protected \Gazelle\Manager\Applicant     $manager;

    public function setUp(): void {
        $this->roleManager = new \Gazelle\Manager\ApplicantRole;
        $this->manager     = new \Gazelle\Manager\Applicant;
    }

    public function tearDown(): void {}

    public function testApplicant() {
        $this->assertIsArray($this->roleManager->list(), 'role-manager-list-published-is-array');
        $new = $this->manager->newApplicantCount();
        $admin = (new Gazelle\Manager\User)->find('@admin');
        $user = (new Gazelle\Manager\User)->find('@user');

        $role = 'published-' . randomString(6);
        $published = $this->roleManager->create($role, 'this is a published role', true, 1);
        $this->assertInstanceOf('\\Gazelle\\ApplicantRole', $published, 'applicant-role-instance');

        $unpublished = $this->roleManager->create('unpublished-' . randomString(6), 'this is an unpublished role', false, $admin->id());


        $this->assertEquals($role, $this->roleManager->title($published->id()), 'role-manager-title');

        $apply = $this->manager->create($user->id(), $published->id(), 'application message');
        $this->assertInstanceOf('\\Gazelle\\Applicant', $apply, 'applicant-instance');
        $this->assertTrue($this->manager->userIsApplicant($user->id()), 'applicant-user-applied');
        $this->assertEquals($new + 1, $this->manager->newApplicantCount(), 'applicant-new-count');
    }
}
