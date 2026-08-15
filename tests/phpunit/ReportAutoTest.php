<?php

namespace Gazelle;

use PHPUnit\Framework\TestCase;
use GazelleUnitTest\Helper;

class ReportAutoTest extends TestCase {
    protected User $user1;
    protected User $user2;

    public function setUp(): void {
        $this->user1 = Helper::makeUser('user.' . randomString(10), 'reportauto', enable: true, clearInbox: true);
        $this->user2 = Helper::makeUser('user.' . randomString(10), 'reportauto', enable: true, clearInbox: true);
    }

    public function tearDown(): void {
        $this->user1->remove();
        $this->user2->remove();
    }

    public function testNew(): void {
        $ratMan = new Manager\ReportAutoType();
        $raMan  = new Manager\ReportAuto();
        $name   = 'ra test new type ' . randomString(10);
        $type   = $ratMan->create($name, 'description', 'ReportAutoTest');

        $r = $raMan->create($this->user1, $type, ["someinterestingkey" => 1234]);
        $this->assertSame(Enum\ReportAutoState::open, $r->state(), 'reportauto-new-state');
        $this->assertSame($type->id, $r->typeId(), 'reportauto-new-typeid');
        $this->assertFalse($r->isClaimed(), 'reportauto-new-claimed');
        $this->assertNull($r->resolved(), 'reportauto-new-resolved');
        $this->assertFalse($r->hasComments(), 'reportauto-new-comments');
        $this->assertNull($r->ownerId(), 'reportauto-new-owner');
        $this->assertSame($this->user1->id, $r->userId(), 'reportauto-new-user');
        $this->assertSame("[ReportAutoTest] $name", $r->text(), 'reportauto-new-text');
        $this->assertSame(["someinterestingkey" => 1234], $r->data(), 'reportauto-new-data');
        $this->assertStringContainsString('someinterestingkey', $r->details(), 'reportauto-new-details-1');
        $this->assertStringContainsString('1234', $r->details(), 'reportauto-new-details-2');
    }

    public function testResolve(): void {
        $ratMan = new Manager\ReportAutoType();
        $raMan  = new Manager\ReportAuto();
        $name   = 'ra test resolve type ' . randomString(10);
        $type   = $ratMan->create($name, 'description', 'ReportAutoTest');

        $r = $raMan->create($this->user1, $type, ["someinterestingkey" => 1234]);
        $r->resolve($this->user2);
        $this->assertInstanceOf(\DateTimeImmutable::class, $r->resolved(), 'reportauto-resolve-1');
        $this->assertSame($this->user2->id, $r->ownerId(), 'reportauto-resolve-2');
        $r->unresolve($this->user2);
        $this->assertNull($r->resolved(), 'reportauto-resolve-3');
        $this->assertSame($this->user2->id, $r->ownerId(), 'reportauto-resolve-4');
        $this->assertTrue($r->isClaimed(), 'reportauto-resolve-5');
    }

    public function testClaim(): void {
        $ratMan = new Manager\ReportAutoType();
        $raMan  = new Manager\ReportAuto();
        $name   = 'ra test claim type ' . randomString(10);
        $type   = $ratMan->create($name, 'description', 'ReportAutoTest');

        $r = $raMan->create($this->user1, $type, ["someinterestingkey" => 1234]);
        $r->claim($this->user2);
        $this->assertTrue($r->isClaimed(), 'reportauto-claim-1');
        $this->assertSame($this->user2->id, $r->ownerId(), 'reportauto-claim-2');
        $r->unclaim();
        $this->assertFalse($r->isClaimed(), 'reportauto-claim-3');
        $this->assertNull($r->ownerId(), 'reportauto-claim-4');
    }

    public function testComments(): void {
        $ratMan = new Manager\ReportAutoType();
        $raMan  = new Manager\ReportAuto();
        $name   = 'ra test comment type ' . randomString(10);
        $type   = $ratMan->create($name, 'description', 'ReportAutoTest');

        $r = $raMan->create($this->user1, $type, ["someinterestingkey" => 1234]);
        $this->assertFalse($r->hasComments(), 'reportauto-comments-1');
        $r->addComment($this->user2, "testcomment");
        $this->assertTrue($r->hasComments(), 'reportauto-comments-2');
        $comments = $r->comments();
        $this->assertCount(1, $comments, 'reportauto-comments-3');
        $this->assertSame($this->user2->id, $comments[0]['id_user'], 'reportauto-comments-4');
        $this->assertSame("testcomment", $comments[0]['comment'], 'reportauto-comments-5');
    }
}
