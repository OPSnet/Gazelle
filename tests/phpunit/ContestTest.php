<?php

namespace Gazelle;

use PHPUnit\Framework\TestCase;
use GazelleUnitTest\Helper;

class ContestTest extends TestCase {
    protected Contest $contest;
    protected array $requestList;
    protected array $torrentList;
    protected array $userList;

    public function setUp(): void {
        $this->userList = [
            Helper::makeUser('contest.' . randomString(6), 'contest', enable: true),
            Helper::makeUser('contest.' . randomString(6), 'contest', enable: true),
        ];
        $this->userList[0]->requestContext()->setViewer($this->userList[0]);
    }

    public function tearDown(): void {
        $db = DB::DB();
        foreach ($this->userList as $user) {
            $db->prepared_query("
                DELETE FROM bonus_pool_contrib WHERE user_id = ?
                ", $user->id
            );
        }
        if (isset($this->contest)) {
            $this->contest->remove();
        }
        if (isset($this->torrentList)) {
            foreach ($this->torrentList as $torrent) {
                Helper::removeTGroup($torrent->group(), $this->userList[0]);
            }
        }
        if (isset($this->requestList)) {
            foreach ($this->requestList as $request) {
                $request->remove();
            }
        }
        $this->userList[0]->requestContext()->unsetViewer();
        foreach ($this->userList as $user) {
            $user->remove();
        }
    }

    public function test00ContestBasic(): void {
        $manager = new Manager\Contest();
        $contestTypes = $manager->contestTypes();
        $this->assertIsArray($contestTypes, 'conteest-manager-array-of-types');
        $this->assertCount(4, array_keys($contestTypes), 'contest-manager-types');
        // these may be a bit fragile, time will tell
        $this->assertEquals(1, $contestTypes[1]['id'], 'contest-type-id');
        $name = 'phpunit contest 00 ' . randomString(10);
        $now  = time();
        $this->contest = $manager->create(
            banner      : 'http://localhost/banner.jpg',
            dateBegin   : date('Y-m-d H:i:s'),
            dateEnd     : date('Y-m-d H:i:s', $now + 86400 * 15),
            name        : $name,
            description : 'phpunit contest description',
            display     : 10,
            hasPool     : false,
            type        : $contestTypes[1]['id'],
        );
        $this->assertEquals($contestTypes[1]['name'], $this->contest->contestType(), 'contest-type');
        $this->assertEquals('http://localhost/banner.jpg', $this->contest->banner(), 'contest-banner');
        $this->assertTrue(Helper::recentDate($this->contest->dateBegin()), 'contest-begin');
        $this->assertTrue($this->contest->isOpen(), 'contest-open');
        $this->assertEquals(
            (int)strtotime($this->contest->dateEnd()),
            $now + 15 * 86400,
            'contest-end',
        );
        $this->assertEquals('phpunit contest description', $this->contest->description(), 'contest-description');
        $this->assertEquals($name, $this->contest->name(), 'contest-name');
        $this->assertFalse($this->contest->hasBonusPool(), 'contest-without-pool');
        $this->assertEquals(0, $this->contest->calculateLeaderboard(), 'contest-no-leaderboard-yet');
        $this->assertEquals(0, $this->contest->totalUsers(), 'contest-no-users-yet');
        $this->assertEquals(0, $this->contest->totalEntries(), 'contest-no-entries-yet');
        $this->assertNull($this->contest->rank($this->userList[0]), 'contest-no-rank-yet');
        $this->assertEquals('none', $this->contest->bonusStatus(), 'contest-status-no-bonus-pool');
        $this->assertEquals(
            $this->contest->id,
            $manager->findById($this->contest->id)?->id,
            'contest-find-by-id',
        );

        $this->assertEquals(1, $this->contest->remove(), 'contest-remove');
        unset($this->contest);
    }

    public function testContestUploadFlacSimple(): void {
        $manager = new Manager\Contest();
        $contestTypes = $manager->contestTypes();
        $this->assertEquals('upload-flac', $contestTypes[1]['name'], 'ct-up-flac-type-name');
        $this->contest = $manager->create(
            banner      : 'http://localhost/banner.jpg',
            dateBegin   : date('Y-m-d H:i:s', time() - 1),
            dateEnd     : new \DateTime()->add(new \DateInterval("P15D"))->format('Y-m-d H:i:s'),
            name        : 'phpunit contest upload flac ' . randomString(6),
            description : 'phpunit contest description',
            display     : 10,
            hasPool     : true,
            type        : $contestTypes[1]['id'],
        );
        $this->assertTrue($this->contest->hasBonusPool(), 'ct-up-flac-has-pool');
        $this->assertEquals("open", $this->contest->bonusStatus(), 'ct-up-flac-pool-open');

        [$sql, $args] = $this->contest->type()->ranker();
        $this->assertIsString($sql, 'ct-up-flac-ranker-sql');
        $this->assertIsArray($args, 'ct-up-flac-ranker-args');
        DB::DB()->prepared_query($sql, ...$args);
        $this->assertEquals(
            [],
            DB::DB()->to_array(false, MYSQLI_ASSOC),
            'ct-up-flac-ranker-output'
        );

        // need to refresh last access date
        $this->userList[0]->refreshLastAccess();
        $this->userList[1]->refreshLastAccess();
        $userMan = new Manager\User();
        $userMan->refreshLastAccess();

        // add first entry
        $this->torrentList = [
            Helper::makeTorrentMusic(
                Helper::makeTGroupMusic(
                    $this->userList[0],
                    'Some ' . randomString(8) . ' contest songs',
                    [[ARTIST_MAIN], ['phpunit contest ' . randomString(6)]],
                    ['pop'],
                ),
                $this->userList[0],
                seed: true,
            ),
        ];
        $this->assertEquals(1, $this->contest->calculateLeaderboard(), 'ct-up-flac-leaderboard-1');
        $this->assertEquals(1, $this->contest->totalUsers(), 'ct-up-flac-user-1');
        $this->assertEquals(1, $this->contest->totalEntries(), 'ct-up-flac-entry-1');
        $this->assertEquals(
            [
                "position" => 1,
                "total"    => 1,
            ],
            $this->contest->rank($this->userList[0]),
            'ct-up-flac-rank-1',
        );
        $this->assertNull($this->contest->rank($this->userList[1]), 'ct-up-flac-noentry-1');

        // add second entry by another user
        $this->torrentList[] = Helper::makeTorrentMusic(
            Helper::makeTGroupMusic(
                $this->userList[1],
                'Some more ' . randomString(8) . ' contest songs',
                [[ARTIST_MAIN], ['phpunit contest ' . randomString(6)]],
                ['jazz'],
            ),
            $this->userList[1],
            seed: true,
        );
        $this->contest->flush();
        $this->assertEquals(2, $this->contest->calculateLeaderboard(), 'ct-up-flac-leaderboard-2');
        $this->assertEquals(2, $this->contest->totalUsers(), 'ct-up-flac-user-2');
        $this->assertEquals(2, $this->contest->totalEntries(), 'ct-up-flac-entry-2');
        $this->assertEquals(
            [
                "position" => 1,
                "total"    => 1,
            ],
            $this->contest->rank($this->userList[0]),
            'ct-up-flac-rank-2-1',
        );
        $this->assertEquals(
            [
                "position" => 2,
                "total"    => 1,
            ],
            $this->contest->rank($this->userList[1]),
            'ct-up-flac-rank-2-2',
        );

        // add second entry by second user
        $this->torrentList[] = Helper::makeTorrentMusic(
            Helper::makeTGroupMusic(
                $this->userList[1],
                'Some live ' . randomString(8) . ' contest songs',
                [[ARTIST_MAIN], ['phpunit contest ' . randomString(6)]],
                ['disco'],
            ),
            $this->userList[1],
            seed: true,
        );
        $this->contest->flush();
        $this->assertEquals(2, $this->contest->calculateLeaderboard(), 'ct-up-flac-leaderboard-3');
        $this->assertEquals(2, $this->contest->totalUsers(), 'ct-up-flac-user-3');
        $this->assertEquals(3, $this->contest->totalEntries(), 'ct-up-flac-entry-3');
        $this->assertEquals(
            [
                "position" => 2,
                "total"    => 1,
            ],
            $this->contest->rank($this->userList[0]),
            'ct-up-flac-rank-3-1',
        );
        $this->assertEquals(
            [
                "position" => 1,
                "total"    => 2,
            ],
            $this->contest->rank($this->userList[1]),
            'ct-up-flac-rank-3-2',
        );

        // add some BP
        $this->userList[] = Helper::makeUser(
            'contest.' . randomString(6),
            'bonus',
            enable: true
        );
        $donor = end($this->userList);
        $donor->refreshLastAccess();
        $userMan->refreshLastAccess();
        $donorBonus = new User\Bonus($donor);
        $points = 100000;
        $donorBonus->setPoints($points);
        $this->assertInstanceOf(
            BonusPool::class,
            $this->contest->bonusPool(),
            'ct-up-flac-bonus-pool'
        );
        $this->assertTrue(
            $donorBonus->donate($this->contest->bonusPool(), $points),
            'ct-up-flac-pool-donate',
        );
        $this->assertEquals(
            $points * 0.9,
            $this->contest->bonusPoolTotal(),
            'ct-up-flac-pool-total'
        );

        // add an upload that does not count
        $this->torrentList[] = Helper::makeTorrentMusic(
            current($this->torrentList)->group(),
            $this->userList[2],
            format   : 'MP3',
            encoding : '320',
            seed     : true,
        );
        $this->contest->flush();
        $this->assertEquals(2, $this->contest->calculateLeaderboard(), 'ct-up-flac-leaderboard-4');
        $this->assertEquals(2, $this->contest->totalUsers(), 'ct-up-flac-user-no-mp3');
        $this->assertEquals(3, $this->contest->totalEntries(), 'ct-up-flac-entry-no-mp3');

        // close the contest
        $this->assertFalse($this->contest->paymentReady(), 'ct-up-flac-payment-not-ready');
        $this->contest->setField(
                'date_end',
                date('Y-m-d H:i:s', time())
            )
            ->modify();
        Helper::sleepTick();
        $this->assertTrue($this->contest->paymentReady(), 'ct-up-flac-payment-ready');

        new Stats\Users()->refresh(); // calculate total enabled users
        $this->assertGreaterThanOrEqual(
            3,
            $this->contest->doPayout(dryrun: false),
            'ct-up-flac-do-payout',
        );

        $this->assertEquals(
            $this->contest->bonusPerContestValue()
                + $this->contest->bonusPerUserValue()
                + $this->contest->bonusPerEntryValue(),
            $this->userList[0]->flush()->bonusPointsTotal(),
            "ct-up-bonus-payout-0-{$this->userList[0]->id}"
        );
        $this->assertEquals(
            $this->contest->bonusPerContestValue()
                + $this->contest->bonusPerUserValue()
                + 2 * $this->contest->bonusPerEntryValue(),
            $this->userList[1]->flush()->bonusPointsTotal(),
            "ct-up-bonus-payout-1-{$this->userList[1]->id}"
        );
        $this->assertEquals(
            $this->contest->bonusPerUserValue(),
            $this->userList[2]->flush()->bonusPointsTotal(),
            "ct-up-bonus-payout-2-{$this->userList[2]->id}"
        );
        $this->assertEquals(1, $this->contest->setPaymentClosed(), 'ct-up-flac-payout-closed');
    }

    public function testContestRequestFill(): void {
        $manager  = new Manager\Contest();
        $reqFill = $manager->contestTypes()[2];
        $this->assertEquals('request-fill', $reqFill['name'], 'ct-reqfill-type');
        $this->contest = $manager->create(
            banner      : 'http://localhost/banner.jpg',
            dateBegin   : date('Y-m-d H:i:s', time() - 1),
            dateEnd     : date('Y-m-d H:i:s', time() + 10),
            name        : 'phpunit contest request fill ' . randomString(6),
            description : 'phpunit contest description request fill',
            display     : 10,
            hasPool     : true,
            type        : $reqFill['id'],
        );
        $this->userList[0]->addBounty(200 * 1024 ** 3);
        $this->userList[1]->addBounty(100 * 1024 ** 3);
        $this->requestList = [
            Helper::makeRequestMusic($this->userList[0], 'phpunit contest request fill 0-1'),
            Helper::makeRequestMusic($this->userList[0], 'phpunit contest request fill 0-2'),
            Helper::makeRequestMusic($this->userList[1], 'phpunit contest request fill 1-1'),
        ];
        foreach ($this->requestList as $r) {
            // backdate the requests prior to the beginning of the contest, add bounty
            $r->setField('created', date('Y-m-d H:i:s', time() - 10))->modify();
            $r->vote($this->userList[0], 1024 * 1024);
        }
        $artist = 'phpunit contest ' . randomString(6);
        $this->torrentList = [
            Helper::makeTorrentMusic(
                Helper::makeTGroupMusic(
                    $this->userList[0], randomString(8), [[ARTIST_MAIN], [$artist]], ['experimental'],
                ),
                $this->userList[0],
                seed: true,
            ),
            Helper::makeTorrentMusic(
                Helper::makeTGroupMusic(
                    $this->userList[0], randomString(8), [[ARTIST_MAIN], [$artist]], ['dubstep'],
                ),
                $this->userList[0],
                seed: true,
            ),
            Helper::makeTorrentMusic(
                Helper::makeTGroupMusic(
                    $this->userList[0], randomString(8), [[ARTIST_MAIN], [$artist]], ['space.rock'],
                ),
                $this->userList[1],
                seed: true,
            ),
        ];

        // first request is a self-fill, contest leaderboard should be empty
        $this->assertEquals(
            1,
            $this->requestList[0]->fill($this->userList[0], $this->torrentList[0]),
            'ct-reqfill-0'
        );
        $this->assertEquals(0, $this->contest->calculateLeaderboard(), 'ct-reqfill-leaderboard-1');
        $this->assertEquals(0, $this->contest->totalUsers(), 'ct-reqfill-user-1');
        $this->assertEquals(0, $this->contest->totalEntries(), 'ct-reqfill-entry-1');

        // user 0 fills for user 1
        $this->assertEquals(
            1,
            $this->requestList[2]->fill($this->userList[0], $this->torrentList[1]),
            'ct-reqfill-1'
        );
        $this->contest->flush();
        $this->assertEquals(1, $this->contest->calculateLeaderboard(), 'ct-reqfill-leaderboard-2');
        $this->assertEquals(1, $this->contest->totalUsers(), 'ct-reqfill-user-2');
        $this->assertEquals(1, $this->contest->totalEntries(), 'ct-reqfill-entry-2');

        // user 1 fills for user 0
        $this->assertEquals(
            1,
            $this->requestList[1]->fill($this->userList[1], $this->torrentList[2]),
            'ct-reqfill-2'
        );
        $this->contest->flush();
        $this->assertEquals(2, $this->contest->calculateLeaderboard(), 'ct-reqfill-leaderboard-3');
        $this->assertEquals(2, $this->contest->totalUsers(), 'ct-reqfill-user-3');
        $this->assertEquals(2, $this->contest->totalEntries(), 'ct-reqfill-entry-3');

        // unfill first request, and fill with other user
        $this->requestList[0]->unfill($this->userList[0], 'phpunit contest', new Manager\Torrent());
        $this->requestList[0]->fill($this->userList[1], $this->torrentList[2]);
        $this->contest->flush();
        $this->assertEquals(2, $this->contest->calculateLeaderboard(), 'ct-reqfill-leaderboard-4');
        $this->assertEquals(2, $this->contest->totalUsers(), 'ct-reqfill-user-4');
        $this->assertEquals(3, $this->contest->totalEntries(), 'ct-reqfill-entry-4');

        $this->assertEquals(
            [[
                'FillerID' => $this->userList[1]->id,
                'UserID'   => $this->userList[0]->id,
                'nr'       => 2,
            ]],
            $this->contest->type()->requestPairs(), /* @phpstan-ignore-line it does exist in this particular case */
            'ct-reqfill-pairs',
        );

        // payout
        $donorBonus = new User\Bonus($this->userList[0]);
        $donorBonus->setPoints(1000);
        $this->assertInstanceOf(
            BonusPool::class,
            $this->contest->bonusPool(),
            'ct-reqfill-bonus-pool'
        );
        $donorBonus->donate($this->contest->bonusPool(), 1000);
        $this->contest->paymentReady();
        $this->assertGreaterThanOrEqual(
            2,
            $this->contest->doPayout(dryrun: false),
            'ct-reqfill-do-payout',
        );

        $this->assertEquals(
            $this->contest->bonusPerContestValue()
                + $this->contest->bonusPerUserValue()
                + $this->contest->bonusPerEntryValue(),
            $this->userList[0]->flush()->bonusPointsTotal(),
            "ct-reqfill-bonus-payout-0-{$this->userList[0]->id}"
        );
        $this->assertEquals(
            $this->contest->bonusPerContestValue()
                + $this->contest->bonusPerUserValue()
                + 2 * $this->contest->bonusPerEntryValue(),
            $this->userList[1]->flush()->bonusPointsTotal(),
            "ct-reqfill-bonus-payout-1-{$this->userList[1]->id}"
        );
    }

    public function testContestUploadFlacNoSingle(): void {
        $manager  = new Manager\Contest();
        $noSingle = $manager->contestTypes()[3];
        $this->assertEquals('upload-flac-no-single', $noSingle['name'], 'ct-up-nosngl-type');
        $this->contest = $manager->create(
            banner      : 'http://localhost/banner.jpg',
            dateBegin   : date('Y-m-d H:i:s', time() - 1),
            dateEnd     : date('Y-m-d H:i:s', time() + 10),
            name        : 'phpunit contest upload flac no single ' . randomString(6),
            description : 'phpunit contest description no single',
            display     : 10,
            hasPool     : true,
            type        : $noSingle['id'],
        );

        // check that a Single upload is not counted
        $this->torrentList = [
            Helper::makeTorrentMusic(
                Helper::makeTGroupMusic(
                    $this->userList[0],
                    'Some ' . randomString(8) . ' contest songs',
                    [[ARTIST_MAIN], ['phpunit contest ' . randomString(6)]],
                    ['metal'],
                    new ReleaseType()->findIdByName('Single'),
                ),
                $this->userList[0],
                seed: true,
            ),
        ];
        $this->assertEquals(0, $this->contest->calculateLeaderboard(), 'ct-up-nosngl-leaderboard-1');
        $this->assertEquals(0, $this->contest->totalUsers(), 'ct-up-nosngl-user-1');
        $this->assertEquals(0, $this->contest->totalEntries(), 'ct-up-nosngl-entry-1');

        $this->userList[0]->refreshLastAccess();
        $this->userList[1]->refreshLastAccess();
        new Manager\User()->refreshLastAccess();
        new Stats\Users()->refresh();

        $this->torrentList[] = Helper::makeTorrentMusic(
            Helper::makeTGroupMusic(
                $this->userList[1],
                'Some boppy ' . randomString(8) . ' contest songs',
                [[ARTIST_MAIN], ['phpunit contest ' . randomString(6)]],
                ['hard.bop'],
            ),
            $this->userList[1],
            seed: true,
        );
        $this->contest->flush();
        $this->assertEquals(1, $this->contest->calculateLeaderboard(), 'ct-up-nosngl-leaderboard-2');
        $this->assertEquals(1, $this->contest->totalUsers(), 'ct-up-nosngl-user-2');
        $this->assertEquals(1, $this->contest->totalEntries(), 'ct-up-nosngl-entry-2');

        // payout
        $donorBonus = new User\Bonus($this->userList[1]);
        $donorBonus->setPoints(1000);
        $this->assertInstanceOf(
            BonusPool::class,
            $this->contest->bonusPool(),
            'ct-up-nosng;-bonus-pool'
        );
        $donorBonus->donate($this->contest->bonusPool(), 1000);
        $this->contest->paymentReady();
        $this->assertGreaterThanOrEqual(
            2,
            $this->contest->doPayout(dryrun: false),
            'ct-up-nosngl-do-payout',
        );

        $this->assertEquals(
            $this->contest->bonusPerUserValue(),
            $this->userList[0]->flush()->bonusPointsTotal(),
            "ct-up-nosngl-bonus-payout-0-{$this->userList[0]->id}"
        );
        $this->assertEquals(
            $this->contest->bonusPerContestValue()
                + $this->contest->bonusPerUserValue()
                + $this->contest->bonusPerEntryValue(),
            $this->userList[1]->flush()->bonusPointsTotal(),
            "ct-up-nosngl-bonus-payout-1-{$this->userList[1]->id}"
        );
    }

    public function testContestPerfectFlac(): void {
        $manager  = new Manager\Contest();
        $perfect = $manager->contestTypes()[4];
        $this->assertEquals('upload-perfect-flac', $perfect['name'], 'ct-perfect-type');
        $this->contest = $manager->create(
            banner      : 'http://localhost/banner.jpg',
            dateBegin   : date('Y-m-d H:i:s', time() - 1),
            dateEnd     : date('Y-m-d H:i:s', time() + 10),
            name        : 'phpunit contest perfect ' . randomString(6),
            description : 'phpunit contest description perfect',
            display     : 10,
            hasPool     : true,
            type        : $perfect['id'],
        );
        $this->torrentList = [
            Helper::makeTorrentMusic(
                Helper::makeTGroupMusic(
                    $this->userList[0],
                    randomString(8),
                    [[ARTIST_MAIN], ['phpunit contest ' . randomString(6)]],
                    ['classical.era'],
                ),
                $this->userList[0],
                media : 'CD',
                seed  : true,
            ),
        ];
        $this->torrentList[0]
            ->setField('HasCue',      '1')
            ->setField('HasLog',      '1')
            ->setField('HasLogDB',    '1')
            ->setField('LogChecksum', '1')
            ->setField('LogScore',    100)
            ->modify();
        $this->userList[0]->refreshLastAccess();
        new Manager\User()->refreshLastAccess();

        [$sql, $args] = $this->contest->type()->ranker();
        $this->assertIsString($sql, 'ct-perfect-ranker-sql');
        $this->assertIsArray($args, 'ct-perfect-ranker-args');
        DB::DB()->prepared_query($sql, ...$args);
        $this->assertEquals(
            [[
                "nr"           => 1,
                "last_torrent" => $this->torrentList[0]->id,
                "user_id"      => $this->userList[0]->id,
            ]],
            DB::DB()->to_array(false, MYSQLI_ASSOC),
            'ct-perfect-ranker-output',
        );

        $this->assertEquals(1, $this->contest->calculateLeaderboard(), 'ct-perfect-leaderboard-1');
        $this->assertEquals(1, $this->contest->totalUsers(), 'ct-perfect-user-1');
        $this->assertEquals(1, $this->contest->totalEntries(), 'ct-perfect-entry-1');

        $donorBonus = new User\Bonus($this->userList[0]);
        $donorBonus->setPoints(1000);
        $this->assertInstanceOf(
            BonusPool::class,
            $this->contest->bonusPool(),
            'ct-perfect-bonus-pool'
        );
        $donorBonus->donate($this->contest->bonusPool(), 1000);
        $this->contest->paymentReady();
        $this->assertGreaterThanOrEqual(
            1,
            $this->contest->doPayout(dryrun: false),
            'ct-perfect-do-payout',
        );
    }
}
