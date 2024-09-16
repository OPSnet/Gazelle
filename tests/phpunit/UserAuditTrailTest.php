<?php

namespace Gazelle;

use PHPUnit\Framework\TestCase;
use Gazelle\Enum\UserAuditEvent;

class UserAuditTrailTest extends TestCase {
    protected User $user;
    protected User $admin;

    public function tearDown(): void {
        $this->user->auditTrail()->resetAuditTrail();
        $this->user->remove();
        if (isset($this->admin)) {
            $this->admin->remove();
        }
    }

    public function testAuditTrailCreate(): void {
        $this->user = \GazelleUnitTest\Helper::makeUser('uat.' . randomString(10), 'uat');
        $this->assertInstanceOf(
            \Gazelle\User\AuditTrail::class,
            $this->user->auditTrail(),
            'uat-instanceof',
        );

        $auditTrail = $this->user->auditTrail();
        $id1 = $auditTrail->addEvent(UserAuditEvent::staffNote, 'phpunit first');
        $this->assertIsInt($id1, 'uat-insert');
        $id2 = $auditTrail->addEvent(UserAuditEvent::staffNote, 'phpunit second');
        $this->assertEquals($id1 + 1, $id2, 'uat-second');

        $this->assertCount(2, $auditTrail->eventList(), 'uat-event-list');
        $this->assertEquals(1, $auditTrail->removeEvent($id1), 'uat-event-remove');
        $this->assertCount(1, $auditTrail->eventList(), 'uat-new-event-list');

        $this->assertEquals(1, $auditTrail->resetAuditTrail(), 'uat-remove');
        $this->assertCount(0, $auditTrail->eventList(), 'uat-reset');
    }

    public function testAuditTrailAbsent(): void {
        $this->user = \GazelleUnitTest\Helper::makeUser('uat.' . randomString(10), 'uat');
        $this->assertFalse($this->user->auditTrail()->hasEvent(UserAuditEvent::mfa), 'uat-event-absent');
    }

    public function testAuditTrailMigrate(): void {
        $this->user = \GazelleUnitTest\Helper::makeUser('uat.' . randomString(10), 'uat');
        $staffNoteList = [
            '2033-03-03 03:03:03 - three',
            '2022-02-02 02:02:02 - two',
            '2021-01-01 01:01:01 - one',
        ];
        $this->user->setField('AdminComment', implode("\n\n", $staffNoteList))->modify();

        $auditTrail = $this->user->auditTrail();
        $this->assertFalse($this->user->auditTrail()->hasEvent(UserAuditEvent::historical), 'uat-not-yet-migrated');
        $this->assertGreaterThan(0, $auditTrail->migrate(new \Gazelle\Manager\User()), 'uat-migrate');
        $this->assertTrue($this->user->auditTrail()->hasEvent(UserAuditEvent::historical), 'uat-migrated');

        $eventList = $auditTrail->eventList();
        $this->assertCount(3, $eventList, 'uat-migrated-event-list');
        $this->assertEquals('three', $eventList[0]['note'], 'uat-event-list-0-note');
        $this->assertEquals('two', $eventList[1]['note'], 'uat-event-list-1-note');
        $this->assertEquals('2021-01-01 01:01:01+00', $eventList[2]['created'], 'uat-event-list-r-created');

        $this->assertEquals(0, $auditTrail->migrate(new \Gazelle\Manager\User()), 'uat-already-migrated');
    }

    public function testAuditTrailStaffNote(): void {
        $this->user = \GazelleUnitTest\Helper::makeUser('uat.' . randomString(10), 'uat');
        $auditTrail = $this->user->auditTrail();
        $this->assertFalse($this->user->auditTrail()->hasEvent(UserAuditEvent::historical), 'uat-not-staff-note-migrated');

        $this->user->addStaffNote('admin comment')->modify();
        $this->assertGreaterThan(0, $auditTrail->migrate(new \Gazelle\Manager\User()), 'uat-staff-note-migrated');
        // one for the creation, one for the staff note
        $this->assertCount(2, $auditTrail->eventList(), 'uat-migrated-staff-note-list');
    }

    public function testAuditTrailCreatorStaffNote(): void {
        $this->user  = \GazelleUnitTest\Helper::makeUser('uat.' . randomString(10), 'uat');
        $this->admin = \GazelleUnitTest\Helper::makeUser('uat.adm.' . randomString(10), 'uat');
        $this->user->auditTrail()->resetAuditTrail();

        $this->user->setField('AdminComment', date('Y-m-d H:m:s') . " - One by {$this->admin->username()}")->modify();
        $this->assertGreaterThan(0, $this->user->auditTrail()->migrate(new \Gazelle\Manager\User()), 'uat-staff-note-migrated');
        $this->assertEquals("One.", $this->user->auditTrail()->eventList()[0]['note'], 'uat-staff-note-one');

        $this->user->auditTrail()->resetAuditTrail();
        $this->user->setField('AdminComment', date('Y-m-d H:m:s') . " - Two by {$this->admin->username()}\nReason: Out on the weekend")->modify();
        $this->assertGreaterThan(0, $this->user->auditTrail()->migrate(new \Gazelle\Manager\User()), 'uat-staff-multinote-migrated');
        $this->assertEquals("Two.\nReason: Out on the weekend", $this->user->auditTrail()->eventList()[0]['note'], 'uat-staff-note-two');
    }
}
