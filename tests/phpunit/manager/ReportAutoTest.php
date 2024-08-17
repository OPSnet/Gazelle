<?php

namespace phpunit\manager;

use Helper;
use PHPUnit\Framework\TestCase;

class ReportAutoTest extends TestCase {
    protected static \Gazelle\Manager\ReportAutoType $ratMan;
    protected static \Gazelle\Manager\ReportAuto $raMan;
    protected static \Gazelle\User $user1;
    protected static \Gazelle\User $user2;

    public static function setUpBeforeClass(): void {
        self::$ratMan = new \Gazelle\Manager\ReportAutoType();
        self::$raMan = new \Gazelle\Manager\ReportAuto(self::$ratMan);
        self::$user1 = Helper::makeUser('user.' . randomString(10), 'reportautoman', enable: true, clearInbox: true);
        self::$user2 = Helper::makeUser('user.' . randomString(10), 'reportautoman', enable: true, clearInbox: true);
    }

    public static function tearDownAfterClass(): void {
        self::$user1->remove();
        self::$user2->remove();
    }

    public function testCreate(): void {
        $type = self::$ratMan->create('raman test create type', 'description', 'ReportAutoTest');
        $report = self::$raMan->create(self::$user1, $type, ['a' => 'b']);
        $this->assertGreaterThan(0, $report->id(), 'raman-create-id');

        $report2 = self::$raMan->create(self::$user1, $type, ['a' => 'b']);
        $this->assertNotEquals($report, $report2, 'raman-create-unique');

        $report3 = self::$raMan->create(self::$user1, $type, ['c' => 'd'], '2024-01-01T00:00+0');
        $this->assertStringStartsWith('2024-01-01 00:00', $report3->created(), 'raman-create-time');

        $report3_n = self::$raMan->findById($report3->id());
        $this->assertNotNull($report3_n, 'raman-find-id');
        $this->assertEquals($report3->id(), $report3_n->id(), 'raman-find-identity');
        $this->assertEquals($report3->state(), $report3_n->state(), 'raman-find-identity-status');
        $this->assertEquals($report3->typeId(), $report3_n->typeId(), 'raman-find-identity-typeid');
        $this->assertEquals($report3->ownerId(), $report3_n->ownerId(), 'raman-find-identity-ownerid');
        $this->assertEquals($report3->details(), $report3_n->details(), 'raman-find-identity-details');
    }

    public function testClaimAll(): void {
        $type = self::$ratMan->create('raman test claimall type', 'description', 'ReportAutoTest');
        $type2 = self::$ratMan->create('raman test claimall type2', 'description', 'ReportAutoTest');
        $r1 = self::$raMan->create(self::$user1, $type, ['a' => 'b']);
        $r2 = self::$raMan->create(self::$user1, $type, ['a' => 'b']);
        $r3 = self::$raMan->create(self::$user1, $type2, ['a' => 'b']);
        $r4 = self::$raMan->create(self::$user2, $type, ['a' => 'b']);

        self::$raMan->claimAll(self::$user2, self::$user1->id(), $type->id());

        $r1n = self::$raMan->findById($r1->id());
        $r2n = self::$raMan->findById($r2->id());
        $r3n = self::$raMan->findById($r3->id());
        $r4n = self::$raMan->findById($r4->id());
        $this->assertTrue($r1n->isClaimed(), 'raman-claimall-1-1');
        $this->assertFalse($r1n->isResolved(), 'raman-claimall-1-2');
        $this->assertTrue($r2n->isClaimed(), 'raman-claimall-2-1');
        $this->assertFalse($r2n->isResolved(), 'raman-claimall-2-2');
        $this->assertFalse($r3n->isClaimed(), 'raman-claimall-3-1');
        $this->assertFalse($r3n->isResolved(), 'raman-claimall-3-2');
        $this->assertFalse($r4n->isClaimed(), 'raman-claimall-4-1');
        $this->assertFalse($r4n->isResolved(), 'raman-claimall-4-2');

        self::$raMan->claimAll(self::$user1, self::$user1->id(), $type2->id());

        $r1n = self::$raMan->findById($r1->id());
        $r2n = self::$raMan->findById($r2->id());
        $r3n = self::$raMan->findById($r3->id());
        $r4n = self::$raMan->findById($r4->id());
        $this->assertTrue($r1n->isClaimed(), 'raman-claimall-1-1');
        $this->assertTrue($r2n->isClaimed(), 'raman-claimall-2-1');
        $this->assertTrue($r3n->isClaimed(), 'raman-claimall-3-1');
        $this->assertFalse($r4n->isClaimed(), 'raman-claimall-4-1');

        $this->assertEquals(self::$user2->id(), $r1n->ownerId(), 'raman-claimall-owner-1');
        $this->assertEquals(self::$user2->id(), $r2n->ownerId(), 'raman-claimall-owner-2');
        $this->assertEquals(self::$user1->id(), $r3n->ownerId(), 'raman-claimall-owner-3');
        $this->assertEquals(null, $r4n->ownerId(), 'raman-claimall-owner-4');

        self::$raMan->claimAll(self::$user2, self::$user2->id(), null);
        $r4n = self::$raMan->findById($r4->id());
        $this->assertEquals(self::$user2->id(), $r4n->ownerId(), 'raman-claimall-owner-5');
        $this->assertTrue($r4n->isClaimed(), 'raman-claimall-4-4');
    }

    public function testResolveAll(): void {
        $type = self::$ratMan->create('raman test resolveall type', 'description', 'ReportAutoTest');
        $type2 = self::$ratMan->create('raman test resolveall type2', 'description', 'ReportAutoTest');
        $r1 = self::$raMan->create(self::$user1, $type, ['a' => 'b']);
        $r2 = self::$raMan->create(self::$user1, $type, ['a' => 'b']);
        $r3 = self::$raMan->create(self::$user1, $type2, ['a' => 'b']);
        $r4 = self::$raMan->create(self::$user2, $type, ['a' => 'b']);

        self::$raMan->resolveAll(self::$user2, self::$user1->id(), $type->id());

        $r1n = self::$raMan->findById($r1->id());
        $r2n = self::$raMan->findById($r2->id());
        $r3n = self::$raMan->findById($r3->id());
        $r4n = self::$raMan->findById($r4->id());
        $this->assertTrue($r1n->isClaimed(), 'raman-resolveall-1-1');
        $this->assertTrue($r1n->isResolved(), 'raman-resolveall-1-2');
        $this->assertTrue($r2n->isClaimed(), 'raman-resolveall-2-1');
        $this->assertTrue($r2n->isResolved(), 'raman-resolveall-2-2');
        $this->assertFalse($r3n->isClaimed(), 'raman-resolveall-3-1');
        $this->assertFalse($r3n->isResolved(), 'raman-resolveall-3-2');
        $this->assertFalse($r4n->isClaimed(), 'raman-resolveall-4-1');
        $this->assertFalse($r4n->isResolved(), 'raman-resolveall-4-2');

        self::$raMan->resolveAll(self::$user1, self::$user1->id(), $type2->id());

        $r1n = self::$raMan->findById($r1->id());
        $r2n = self::$raMan->findById($r2->id());
        $r3n = self::$raMan->findById($r3->id());
        $r4n = self::$raMan->findById($r4->id());
        $this->assertTrue($r1n->isResolved(), 'raman-resolveall-1-3');
        $this->assertTrue($r2n->isResolved(), 'raman-resolveall-2-3');
        $this->assertTrue($r3n->isResolved(), 'raman-resolveall-3-3');
        $this->assertFalse($r4n->isResolved(), 'raman-resolveall-4-3');

        $this->assertEquals(self::$user2->id(), $r1n->ownerId(), 'raman-resolveall-owner-1');
        $this->assertEquals(self::$user2->id(), $r2n->ownerId(), 'raman-resolveall-owner-2');
        $this->assertEquals(self::$user1->id(), $r3n->ownerId(), 'raman-resolveall-owner-3');
        $this->assertEquals(null, $r4n->ownerId(), 'raman-resolveall-owner-4');

        self::$raMan->resolveAll(self::$user2, self::$user2->id(), null);
        $r4n = self::$raMan->findById($r4->id());
        $this->assertEquals(self::$user2->id(), $r4n->ownerId(), 'raman-resolveall-owner-5');
        $this->assertTrue($r4n->isResolved(), 'raman-resolveall-4-4');
    }

    public function testDeleteComment(): void {
        $type = self::$ratMan->create('raman test delcomment type', 'description', 'ReportAutoTest');
        $r = self::$raMan->create(self::$user1, $type, ['a' => 'b']);
        $commentId = $r->addComment(self::$user1, "testcomment");
        $this->assertTrue($r->hasComments(), 'raman-delcomment-1');
        $this->assertLessThan(1, self::$raMan->deleteComment($commentId, self::$user2), 'raman-delcomment-2');
        self::$raMan->deleteComment($commentId, self::$user1);
        $r = self::$raMan->findById($r->id());
        $this->assertFalse($r->hasComments(), 'raman-delcomment-3');
    }

    public function testEditComment(): void {
        $type = self::$ratMan->create('raman test editcomment type', 'description', 'ReportAutoTest');
        $r = self::$raMan->create(self::$user1, $type, ['a' => 'b']);
        $commentId = $r->addComment(self::$user1, "testcomment");
        $this->assertLessThan(1, self::$raMan->editComment($commentId, self::$user2, "nomessage"), 'raman-editcomment-1');
        $this->assertEquals("testcomment", $r->comments()[0]['comment'], 'raman-editcomment-2');
        $this->assertEquals(1, self::$raMan->editComment($commentId, self::$user1, "newmessage"), 'raman-editcomment-3');
        $r = self::$raMan->findById($r->id());
        $this->assertEquals("newmessage", $r->comments()[0]['comment'], 'raman-editcomment-4');
    }
}
