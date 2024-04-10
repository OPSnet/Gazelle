<?php

namespace phpunit;

use Helper;
use PHPUnit\Framework\TestCase;

require_once(__DIR__ . '/../../lib/bootstrap.php');
require_once(__DIR__ . '/../helper.php');

class ReportAutoTest extends TestCase {
    protected static \Gazelle\Manager\ReportAutoType $ratMan;
    protected static \Gazelle\Manager\ReportAuto $raMan;
    protected static \Gazelle\User $user1;
    protected static \Gazelle\User $user2;

    public function setUp(): void {
        self::$ratMan = new \Gazelle\Manager\ReportAutoType();
        self::$raMan = new \Gazelle\Manager\ReportAuto(self::$ratMan);
        self::$user1 = Helper::makeUser('user.' . randomString(10), 'reportauto', enable: true, clearInbox: true);
        self::$user2 = Helper::makeUser('user.' . randomString(10), 'reportauto', enable: true, clearInbox: true);
    }

    public static function tearDownAfterClass(): void {
        self::$user1->remove();
        self::$user2->remove();
    }

    public function testNew(): void {
        $type = self::$ratMan->create('ra test new type', 'description', 'ReportAutoTest');
        $r = self::$raMan->create(self::$user1, $type, ["someinterestingkey" => 1234]);
        $this->assertEquals(\Gazelle\Enum\ReportAutoState::open, $r->state(), 'reportauto-new-state');
        $this->assertEquals($type->id(), $r->typeId(), 'reportauto-new-typeid');
        $this->assertFalse($r->isClaimed(), 'reportauto-new-claimed');
        $this->assertFalse($r->isResolved(), 'reportauto-new-resolved');
        $this->assertFalse($r->hasComments(), 'reportauto-new-comments');
        $this->assertNull($r->ownerId(), 'reportauto-new-owner');
        $this->assertEquals(self::$user1->id(), $r->userId(), 'reportauto-new-user');
        $this->assertEquals('[ReportAutoTest] ra test new type', $r->text(), 'reportauto-new-text');
        $this->assertEquals(["someinterestingkey" => 1234], $r->data(), 'reportauto-new-data');
        $this->assertStringContainsString('someinterestingkey', $r->details(), 'reportauto-new-details-1');
        $this->assertStringContainsString('1234', $r->details(), 'reportauto-new-details-2');
    }

    public function testResolve(): void {
        $type = self::$ratMan->create('ra test resolve type', 'description', 'ReportAutoTest');
        $r = self::$raMan->create(self::$user1, $type, ["someinterestingkey" => 1234]);
        $r->resolve(self::$user2);
        $this->assertTrue($r->isResolved(), 'reportauto-resolve-1');
        $this->assertEquals(self::$user2->id(), $r->ownerId(), 'reportauto-resolve-2');
        $r->unresolve(self::$user2);
        $this->assertFalse($r->isResolved(), 'reportauto-resolve-3');
        $this->assertEquals(self::$user2->id(), $r->ownerId(), 'reportauto-resolve-4');
        $this->assertTrue($r->isClaimed(), 'reportauto-resolve-5');
    }

    public function testClaim(): void {
        $type = self::$ratMan->create('ra test claim type', 'description', 'ReportAutoTest');
        $r = self::$raMan->create(self::$user1, $type, ["someinterestingkey" => 1234]);
        $r->claim(self::$user2);
        $this->assertTrue($r->isClaimed(), 'reportauto-claim-1');
        $this->assertEquals(self::$user2->id(), $r->ownerId(), 'reportauto-claim-2');
        $r->unclaim();
        $this->assertFalse($r->isClaimed(), 'reportauto-claim-3');
        $this->assertNull($r->ownerId(), 'reportauto-claim-4');
    }

    public function testComments(): void {
        $type = self::$ratMan->create('ra test comments type', 'description', 'ReportAutoTest');
        $r = self::$raMan->create(self::$user1, $type, ["someinterestingkey" => 1234]);
        $this->assertFalse($r->hasComments(), 'reportauto-comments-1');
        $r->addComment(self::$user2, "testcomment");
        $this->assertTrue($r->hasComments(), 'reportauto-comments-2');
        $comments = $r->comments();
        $this->assertEquals(1, count($comments), 'reportauto-comments-3');
        $this->assertEquals(self::$user2->id(), $comments[0]['id_user'], 'reportauto-comments-4');
        $this->assertEquals("testcomment", $comments[0]['comment'], 'reportauto-comments-5');
    }
}
