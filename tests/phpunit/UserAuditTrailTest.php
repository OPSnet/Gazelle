<?php

namespace Gazelle;

use PHPUnit\Framework\TestCase;
use GazelleUnitTest\Helper;
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
        $this->user = Helper::makeUser('uat.' . randomString(10), 'uat');

        $auditTrail = $this->user->auditTrail();
        $id1 = $auditTrail->addEvent(UserAuditEvent::staffNote, 'phpunit first');
        $this->assertGreaterThan(0, $id1, 'uat-insert');
        $this->assertSame($id1, $auditTrail->lastEventId(), 'uat-last-event');
        $id2 = $auditTrail->addEvent(UserAuditEvent::staffNote, 'phpunit second');
        $this->assertSame($id1 + 1, $id2, 'uat-second');

        $this->assertCount(3, $auditTrail->fullEventList(), 'uat-event-list');
        $this->assertSame(1, $auditTrail->removeEvent($id1), 'uat-event-remove');
        $this->assertCount(2, $auditTrail->fullEventList(), 'uat-new-event-list');

        $this->assertSame(2, $auditTrail->resetAuditTrail(), 'uat-remove');
        $this->assertCount(0, $auditTrail->fullEventList(), 'uat-reset');
        $this->assertSame(0, $auditTrail->lastEventId(), 'uat-no-last-event');
    }

    public function testAuditTrailAbsent(): void {
        $this->user = Helper::makeUser('uat.' . randomString(10), 'uat');
        $this->assertFalse($this->user->auditTrail()->hasEvent(UserAuditEvent::mfa), 'uat-event-absent');
    }

    public function testAuditTrailStaffNote(): void {
        $this->user = Helper::makeUser('uat.' . randomString(10), 'uat');
        $auditTrail = $this->user->auditTrail();
        $this->assertFalse($this->user->auditTrail()->hasEvent(UserAuditEvent::historical), 'uat-not-staff-note-migrated');

        $this->user->addStaffNote('admin comment')->modify();

        // one for the creation, one for the staff note
        $eventList = $auditTrail->fullEventList();
        $this->assertCount(2, $eventList, 'uat-migrated-staff-note-list');
        $this->assertCount(
            2,
            $auditTrail->eventList([
                $eventList[0]['id_audit_trail_user'],
                $eventList[1]['id_audit_trail_user'],
            ]),
            'uat-partial-event-list'
        );
    }

    public function testAuditTrailModify(): void {
        $this->user = Helper::makeUser('uat.' . randomString(10), 'uat');
        $auditTrail = $this->user->auditTrail();
        $auditTrail->resetAuditTrail();
        $id1 = $auditTrail->addEvent(UserAuditEvent::staffNote, 'phpunit first');
        $id2 = $auditTrail->addEvent(UserAuditEvent::staffNote, 'phpunit second');
        $this->assertSame(
            2,
            $auditTrail->modifyEventList([$id1, $id2], 'phpunit rewrite', $this->user),
            'uat-modify-history'
        );
        $eventList = $auditTrail->fullEventList();
        $this->assertCount(1, $eventList, 'uat-after-modify');
        $this->assertSame($this->user->id, $eventList[0]['id_user_creator'], 'uat-creator-after-modify');
        $this->assertSame(
            1,
            $auditTrail->modifyEventList([$id1], 'phpunit second rewrite', $this->user),
            'uat-remodify-history'
        );
        $this->assertSame(
            1,
            $auditTrail->modifyEventList([$id1], '', $this->user),
            'uat-remove-history'
        );
        $this->assertCount(0, $auditTrail->fullEventList(), 'uat-all-gone');
    }

    public function testUserUpdate(): void {
        $this->user  = Helper::makeUser('uat.' . randomString(10), 'uat');
        $this->assertSame($this->user->updated(), $this->user->created(), 'user-updated-is-created');
        $checkpoint = $this->user->checkpoint();
        Helper::sleepTick(); // ensure created != updated
        $this->user->setField('Username', $this->user->username() . 'x')->modify();
        $this->assertNotSame($this->user->updated(), $this->user->created(), 'user-updated-after-created');
        $this->assertNotSame($checkpoint, $this->user->checkpoint(), 'user-new-checkpoint');

        $checkpoint = $this->user->checkpoint();
        $this->user->auditTrail()->addEvent(UserAuditEvent::staffNote, 'update');
        $this->assertNotSame($checkpoint, $this->user->checkpoint(), 'user-newer-checkpoint');
    }

    public function testAuditTrailSearch(): void {
        $this->admin = Helper::makeUser('uat.' . randomString(10), 'uata');
        $adminTrail  = $this->admin->auditTrail();
        $adminTrail->addEvent(UserAuditEvent::activity, 'phpunit activity');
        $adminTrail->addEvent(UserAuditEvent::userclass, 'phpunit userclass');

        $this->user = Helper::makeUser('uat.' . randomString(10), 'uatas');
        $random    = randomString(6);
        $staffNote = "phpunit staff note $random";
        $userTrail = $this->user->auditTrail();
        $userTrail->addEvent(UserAuditEvent::activity, 'phpunit activity');
        $userTrail->addEvent(UserAuditEvent::userclass, 'phpunit userclass');
        $userTrail->addEvent(UserAuditEvent::staffNote, $staffNote, $this->admin);

        // This is a bit of an awful hack. There are users that are created
        // before the unit test suite is run, and they will be noticed in the
        // unit tests for contests. Tidy up that side effect here:
        new DB\Pg(PG_RW_DSN)->query("
            delete from audit_trail_user where note ~ 'phpunit contest request fill'
        ");
        $after = new Search\AuditTrail();
        $after->setCreatedAfter(date('Y-m-d H:i:s', time() - 3600));
        $this->assertSame(
            7, // 2 activity, 2 userclass + staff note + 2 invite
            $after->total(),
            'ats-after-total',
        );
        $page = $after->page(10, 0);
        $this->assertCount(7, $page, 'ats-page-count');
        $this->assertEqualsCanonicalizing(
            ["id_user", "id_user_creator", "created", "event", "note"],
            array_keys($page[0]),
            'ats-page-keys',
        );
        $item = array_first(array_filter(
            $page,
            fn ($a) => $a['event'] === UserAuditEvent::staffNote->value,
        ));
        $this->assertSame($staffNote, $item['note'], 'ats-user-note');

        $before = new Search\AuditTrail();
        $before->setCreatedBefore('2000-01-01 00:00:00');
        $this->assertSame(0, $before->total(), 'ats-before-total');

        $author = new Search\AuditTrail();
        $author->setAuthorList([$this->admin]);
        $this->assertSame(1, $author->total(), 'ats-author-total');
        $item = array_first(array_filter(
            $author->page(2, 0),
            fn ($a) => $a['event'] === UserAuditEvent::staffNote->value,
        ));
        $this->assertSame($staffNote, $item['note'], 'ats-author-note');

        $note = new Search\AuditTrail();
        $note->setNote($random, 'webquery');
        $this->assertSame(1, $note->total(), 'ats-note-total');
        $item = array_first(array_filter(
            $note->page(2, 0),
            fn ($a) => $a['event'] === UserAuditEvent::staffNote->value,
        ));
        $this->assertSame($staffNote, $item['note'], 'ats-note-note');

        $user = new Search\AuditTrail();
        $user->setUserList([$this->user]);
        $this->assertSame(4, $user->total(), 'ats-user-total');
        $this->assertCount(4, $user->page(5, 0), 'ats-user-page');

        $event = new Search\AuditTrail();
        $event->setEventList(['activity', 'userclass']);
        $event->setCreatedAfter(date('Y-m-d H:i:s', time() - 3600));
        $this->assertSame(4, $event->total(), 'ats-event-total');
        $this->assertCount(4, $event->page(5, 0), 'ats-event-page');
    }
}
