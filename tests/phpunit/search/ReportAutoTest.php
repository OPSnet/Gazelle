<?php

namespace phpunit\search;

use Gazelle\Enum\ReportAutoState;
use Helper;
use PHPUnit\Framework\TestCase;

require_once(__DIR__ . '/../../../lib/bootstrap.php');
require_once(__DIR__ . '/../../helper.php');

class ReportAutoTest extends TestCase {
    protected static \Gazelle\Manager\User $userMan;
    protected static \Gazelle\Manager\ReportAutoType $ratMan;
    protected static \Gazelle\Manager\ReportAuto $raMan;
    protected static \Gazelle\User $user1;
    protected static \Gazelle\User $user2;
    protected static \Gazelle\ReportAuto\Type $type1;
    protected static \Gazelle\ReportAuto\Type $type2;
    protected static \Gazelle\ReportAuto $report;

    protected static function createReports(int $n, \Gazelle\User $user, \Gazelle\ReportAuto\Type $type, \Gazelle\User $owner): array {
        $reports = [];
        for ($i = 0; $i < $n; $i++) {
            $reports[] = self::$raMan->create($user, $type, ["i" => $i]);
        }
        $reports[0]->claim($owner);
        $reports[1]->resolve($owner);
        return $reports;
    }

    public static function setUpBeforeClass(): void {
        self::$userMan = new \Gazelle\Manager\User();
        self::$ratMan = new \Gazelle\Manager\ReportAutoType();
        self::$raMan = new \Gazelle\Manager\ReportAuto(self::$ratMan);
        self::$user1 = Helper::makeUser('user.' . randomString(10), 'reportautosearch', enable: true, clearInbox: true);
        self::$user2 = Helper::makeUser('user.' . randomString(10), 'reportautosearch', enable: true, clearInbox: true);
        self::$type1 = self::$ratMan->create('rasearch test type1', '');
        self::$type2 = self::$ratMan->create('rasearch test type2', '');

        self::createReports(3, self::$user1, self::$type1, self::$user2);
        self::createReports(5, self::$user2, self::$type1, self::$user1);
        self::createReports(7, self::$user1, self::$type2, self::$user2);
        $r = self::createReports(11, self::$user2, self::$type2, self::$user1);
        $r[2]->claim(self::$user2);
        $r[3]->resolve(self::$user2);
        $r[4]->resolve(self::$user2);
        self::$report = $r[5];
    }

    public static function tearDownAfterClass(): void {
        self::$user1->remove();
        self::$user2->remove();
    }

    protected function matchThingList(array $thingList, \Gazelle\User|\Gazelle\ReportAuto\Type $matcher, int $n, string $msg): void {
        foreach ($thingList as $list) {
            match ($list[0]->id()) {
                $matcher->id() => $this->assertEquals($n, $list[1], "rasearch-$msg"),
                default => $this->fail("rasearch-$msg-fail")
            };
        }
    }

    public function testAll(): void {
        $search = new \Gazelle\Search\ReportAuto(self::$raMan, self::$ratMan);

        foreach ($search->typeTotalList() as $typeList) {
            match ($typeList[0]) {
                self::$type1 => $this->assertEquals(3 + 5, $typeList[1], 'rasearch-all-2'),
                self::$type2 => $this->assertEquals(7 + 11, $typeList[1], 'rasearch-all-3'),
                default => null,  // created by other tests
            };
        }

        foreach ($search->userTotalList(self::$userMan) as $userList) {
            match ($userList[0]) {
                self::$user1 => $this->assertEquals(3 + 7, $userList[1], 'rasearch-all-4'),
                self::$user2 => $this->assertEquals(5 + 11, $userList[1], 'rasearch-all-5'),
                default => null,  // created by other tests
            };
        }
    }

    public function testStateOpen(): void {
        $search = new \Gazelle\Search\ReportAuto(self::$raMan, self::$ratMan);
        $search->setState(\Gazelle\Enum\ReportAutoState::open);
        $search->setType(self::$type1);

        $this->matchThingList($search->typeTotalList(), self::$type1, 3 + 5 - 2, 'state');

        $this->assertEquals(2, count($search->page(10, 0)), 'rasearch-page-1');
        $this->assertEquals(1, count($search->page(1, 0)), 'rasearch-page-2');
        $this->assertEquals(1, count($search->page(1, 1)), 'rasearch-page-3');
        $this->assertEquals(0, count($search->page(1, 2)), 'rasearch-page-4');
    }

    public function testStateInprogress(): void {
        $search = new \Gazelle\Search\ReportAuto(self::$raMan, self::$ratMan);
        $search->setState(\Gazelle\Enum\ReportAutoState::in_progress);
        $search->setType(self::$type2);

        $this->matchThingList($search->typeTotalList(), self::$type2, 3, 'state-inprogress');
    }

    public function testStateClosed(): void {
        $search = new \Gazelle\Search\ReportAuto(self::$raMan, self::$ratMan);
        $search->setState(\Gazelle\Enum\ReportAutoState::closed);
        $search->setType(self::$type2);

        $this->matchThingList($search->typeTotalList(), self::$type2, 4, 'state-closed');
    }

    public function testUnclaimed(): void {
        $search = new \Gazelle\Search\ReportAuto(self::$raMan, self::$ratMan);
        $search->setState(\Gazelle\Enum\ReportAutoState::open);
        $search->setOwner(null);
        $search->setType(self::$type2);

        $this->matchThingList($search->typeTotalList(), self::$type2, 7 + 11 - 2 - 2 - 3, 'unclaimed');
    }

    public function testClosed1(): void {
        $search = new \Gazelle\Search\ReportAuto(self::$raMan, self::$ratMan);
        $search->setState(\Gazelle\Enum\ReportAutoState::closed);
        $search->setOwner(self::$user1);

        $this->matchThingList($search->userTotalList(self::$userMan), self::$user2, 2, 'closed-1');
    }

    public function testClosed2(): void {
        $search = new \Gazelle\Search\ReportAuto(self::$raMan, self::$ratMan);
        $search->setState(\Gazelle\Enum\ReportAutoState::closed);
        $search->setOwner(self::$user2);

        foreach ($search->userTotalList(self::$userMan) as $userList) {
            match ($userList[0]->id()) {
                self::$user1->id() => $this->assertEquals(2, $userList[1], 'rasearch-closed-2-1'),
                self::$user2->id() => $this->assertEquals(2, $userList[1], 'rasearch-closed-2-2'),
                default => $this->fail('rasearch-closed-2-fail')
            };
        }
    }

    public function testClaimed1(): void {
        $search = new \Gazelle\Search\ReportAuto(self::$raMan, self::$ratMan);
        $search->setState(\Gazelle\Enum\ReportAutoState::in_progress);
        $search->setOwner(self::$user2);

        foreach ($search->userTotalList(self::$userMan) as $userList) {
            match ($userList[0]->id()) {
                self::$user1->id() => $this->assertEquals(2, $userList[1], 'rasearch-claimed-1-1'),
                self::$user2->id() => $this->assertEquals(1, $userList[1], 'rasearch-claimed-1-2'),
                default => $this->fail('rasearch-claimed-1-fail')
            };
        }
    }

    public function testClaimed2(): void {
        $search = new \Gazelle\Search\ReportAuto(self::$raMan, self::$ratMan);
        $search->setState(\Gazelle\Enum\ReportAutoState::in_progress);
        $search->setOwner(self::$user2);
        $search->setType(self::$type1);

        $this->matchThingList($search->typeTotalList(), self::$type1, 1, 'claimed-2');
    }

    public function testUser(): void {
        $search = new \Gazelle\Search\ReportAuto(self::$raMan, self::$ratMan);
        $search->setState(\Gazelle\Enum\ReportAutoState::open);
        $search->setUser(self::$user1);

        $this->matchThingList($search->userTotalList(self::$userMan), self::$user1, 3 + 7 - 2, 'user-open');
    }

    public function testId(): void {
        $search = new \Gazelle\Search\ReportAuto(self::$raMan, self::$ratMan);
        $search->setId(self::$report->id());

        $this->matchThingList($search->typeTotalList(), self::$ratMan->findById(self::$report->typeId()), 1, 'id-1');
    }

    public function testIdNoExist(): void {
        $search = new \Gazelle\Search\ReportAuto(self::$raMan, self::$ratMan);
        $search->setId(12345);
        $this->assertEquals(0, $search->total(), 'rasearch-id-2');
    }
}
