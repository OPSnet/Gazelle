<?php

use \PHPUnit\Framework\TestCase;

/**
 * Note to Developers/Testers
 * ==========================
 *
 * In the context of a local development environment, this unit
 * test is fairly sensitive to parameters outside its control.
 * Tests may fail because of _other_ unseeded/never seeded uploads
 * that you have created locally.
 *
 * If you receive errors (notably never-initial-0-count or
 * unseeded-initial-0-count) the tests are likely catching your
 * uploads in its net. To work around this:
 *
 * UPDATE torrents_leech_stats SET last_action = now()
 *
 * will make the tests happy and everything should succeed. Any
 * other problems are your own.
 */

require_once(__DIR__ . '/../../lib/bootstrap.php');
require_once(__DIR__ . '/../helper.php');

class ReaperTest extends TestCase {
    protected string $tgroupName;
    protected array $torrentList = [];
    protected array $userList    = [];

    public function setUp(): void {
        // we need two users, one who uploads and one who snatches
        $this->userList = [
            Helper::makeUser('reaper.' . randomString(10), 'reaper'),
            Helper::makeUser('reaper.' . randomString(10), 'reaper'),
        ];
        // enable them and wipe their inboxes (there is only one message)
        foreach ($this->userList as $user) {
            $user->setUpdate('Enabled', '1')->modify();
            $pmMan = new Gazelle\Manager\PM($user);
            foreach ((new Gazelle\User\Inbox($user))->messageList($pmMan, 1, 0) as $pm) {
                $pm->remove();
            }
        }

        // create a torrent group
        $this->tgroupName = 'phpunit reaper ' . randomString(6);
        $tgroup = Helper::makeTGroupMusic(
            name:       $this->tgroupName,
            artistName: [[ARTIST_MAIN], ['Reaper Girl ' . randomString(12)]],
            tagName:    ['electronic'],
            user:       $this->userList[0],
        );

        // and add some torrents to the group
        $this->torrentList = array_map(fn($info) =>
            Helper::makeTorrentMusic(
                tgroupId:    $tgroup->id(),
                user:        $this->userList[0],
                title:       $info['title'],
            ), [
                ['title' => 'Deluxe Edition'],
                ['title' => 'Limited Edition'],
            ]
        );
    }

    public function tearDown(): void {
        $this->removeUnseededAlert($this->torrentList);
        $tgroup = $this->torrentList[0]->group();
        $torMan = new Gazelle\Manager\Torrent;
        foreach ($this->torrentList as $torrent) {
            $torrent = $torMan->findById($torrent->id());
            if (is_null($torrent)) {
                continue;
            }
            [$ok, $message] = $torrent->remove($this->userList[0], 'reaper unit test');
            if (!$ok) {
                print "error $message [{$this->userList[0]->id()}]\n";
            }
        }
        $tgroup->remove($this->userList[0]);

        foreach ($this->userList as $user) {
            $user->remove();
        }
    }

    // --- HELPER FUNCTIONS ----

    protected function generateReseed(Gazelle\Torrent $torrent, Gazelle\User $user): void {
        $db = Gazelle\DB::DB();
        $db->prepared_query("
            UPDATE torrents_leech_stats SET last_action = now() WHERE TorrentID = ?
            ", $torrent->id()
        );
        $db->prepared_query("
            INSERT INTO xbt_files_users
                   (fid, uid, useragent, peer_id, active, remaining, ip, timespent, mtime)
            VALUES (?,   ?,   ?,         ?,       1, 0, '127.0.0.1', 1, unix_timestamp(now() - interval 1 hour))
            ",  $torrent->id(), $user->id(), 'ua-' . randomString(12), randomString(20)
        );
    }

    protected function generateSnatch(Gazelle\Torrent $torrent, Gazelle\User $user): void {
        Gazelle\DB::DB()->prepared_query("
            INSERT INTO xbt_snatched
                   (fid, uid, tstamp, IP, seedtime)
            VALUES (?,   ?,   unix_timestamp(now()), '127.0.0.1', 1)
            ", $torrent->id(), $user->id()
        );
    }

    protected function modifyLastAction(Gazelle\Torrent $torrent, int $interval): void {
        Gazelle\DB::DB()->prepared_query("
            UPDATE torrents_leech_stats SET
                last_action = now() - INTERVAL ? HOUR
            WHERE TorrentID = ?
            ", $interval, $torrent->id()
        );
    }

    protected function modifyUnseededInterval(Gazelle\Torrent $torrent, int $hour): void {
        Gazelle\DB::DB()->prepared_query("
            UPDATE torrent_unseeded SET
                unseeded_date = ?
            WHERE torrent_id = ?
            ", date('Y-m-d H:i:s', (int)strtotime("-{$hour} hours")), $torrent->id()
        );
    }

    protected function removeUnseededAlert(array $list): void {
        Gazelle\DB::DB()->prepared_query("
            DELETE FROM torrent_unseeded WHERE torrent_id in (" . placeholders($list) . ")"
            , ...array_map(fn($t) => $t->id(), $list)
        );
    }

    /**
     * This method is not necessary per se, but came in handy when debugging the tests
     */
    protected function status(array $torrentList): void {
        $db = Gazelle\DB::DB();
        $db->prepared_query("
            SELECT t.ID,
                t.Time,
                tu.unseeded_date < tls.last_action,
                coalesce(tls.last_action, 'null'),
                coalesce(tu.unseeded_date, 'null'),
                coalesce(tu.notify, 'null'),
                coalesce(tu.state, 'null')
            FROM torrents t
            INNER JOIN torrents_leech_stats tls ON (tls.TorrentID = t.ID)
            LEFT JOIN torrent_unseeded tu ON (tu.torrent_id = t.ID)
            WHERE t.ID IN (" . placeholders($torrentList) . ")"
            , ...array_map(fn($t) => $t->id(), $torrentList)
        );
        echo implode("\t", ['id', 'created', 'created<last?', 'last_action', 'unseeded', 'final', 'never_seeded']), "\n";
        echo implode("\n",
            array_map(fn($r) => implode("\t", $r), $db->to_array(false, MYSQLI_NUM, false))
        ) . "\n";
    }

    // -------------------------

    public function testExpand(): void {
        $reaper = new \Gazelle\Torrent\Reaper(new Gazelle\Manager\Torrent, new Gazelle\Manager\User);

        $this->assertEquals(
            [456 => [99, 88, 77]],
            $reaper->expand(100, [[456, '99,88,77']]),
            'torrent-reaper-expand-1',
        );

        $this->assertEquals(
            [
                456 => [99, 88, 77],
                567 => [10, 20, 30, 40, 50],
                678 => [321],
            ],
            $reaper->expand(100, [
                [456, '99,88,77'],
                [567, '10,20,30,40,50'],
                [678, '321'],
            ]), 'torrent-reaper-expand-2',
        );

        $this->assertEquals(
            [
                456 => [99, 88],
                567 => [10, 20],
                678 => [321],
            ],
            $reaper->expand(2, [
                [456, '99,88,77'],
                [567, '10,20,30,40,50'],
                [678, '321'],
            ]),
            'torrent-reaper-expand-limit',
        );
    }

    public function testNeverSeeded(): void {
        $user = $this->torrentList[0]->uploader();
        $pmMan = new Gazelle\Manager\PM($user);
        $inbox = new Gazelle\User\Inbox($user);
        $this->assertEquals(0, $inbox->messageTotal(), 'never-inbox-initial');

        $torMan  = new Gazelle\Manager\Torrent;
        $userMan = new Gazelle\Manager\User;
        $reaper  = new \Gazelle\Torrent\Reaper($torMan, $userMan);
        $initialUnseededStats = $reaper->stats();

        // reset the time of the never seeded alert back in time to hit the initial timeout
        $hour = NOTIFY_NEVER_SEEDED_INITIAL_HOUR + 1;
        $neverSeededInitialDate = date('Y-m-d H:i:s', strtotime("-{$hour} hours"));
        $this->torrentList[0]->setUpdate('Time', $neverSeededInitialDate)->modify();

        // look for never seeded
        $neverInitial = $reaper->initialNeverSeededList();
        $this->assertCount(1, $neverInitial, 'never-initial-0-count');
        $this->assertEquals(
            1,
            $reaper->process(
                $neverInitial,
                Gazelle\Torrent\ReaperState::NEVER,
                Gazelle\Torrent\ReaperNotify::INITIAL
            ),
            'never-initial-process'
        );
        $this->assertEquals(
            [
                "never_seeded_initial" => $initialUnseededStats['never_seeded_initial'] + 1,
                "never_seeded_final"   => 0,
                "unseeded_initial"     => 0,
                "unseeded_final"       => 0,
            ],
            $reaper->stats(),
            'reaper-unseeded-stats-0'
        );

        // check the notification
        $this->assertEquals(1, $inbox->messageTotal(), 'never-message-initial-count');
        $pm = $inbox->messageList($pmMan, 1, 0)[0];
        $this->assertEquals('You have a non-seeded new upload to rescue', $pm->subject(), 'never-message-initial-subject');
        $pm->remove();

        // reset the unseeded entries and time out a second upload
        $this->removeUnseededAlert($this->torrentList);
        $this->torrentList[1]->setUpdate('Time', $neverSeededInitialDate)->modify();

        $neverInitial = $reaper->initialNeverSeededList();
        $this->assertCount(1, $neverInitial, 'never-initial-2'); // one user ...
        $this->assertEquals(2,                            // ... with two uploads
            $reaper->process(
                $neverInitial,
                Gazelle\Torrent\ReaperState::NEVER,
                Gazelle\Torrent\ReaperNotify::INITIAL
            ),
            'never-2-process'
        );
        $this->assertEquals(
            [
                "never_seeded_initial" => $initialUnseededStats['never_seeded_initinal'] + 2,
                "never_seeded_final"   => 0,
                "unseeded_initial"     => 0,
                "unseeded_final"       => 0,
            ],
            $reaper->stats(),
            'reaper-unseeded-stats-1'
        );

        $this->assertEquals(1, $inbox->messageTotal(), 'never-message-2');
        $pm   = $inbox->messageList($pmMan, 1, 0)[0];
        $body = $pm->postList(1, 0)[0]['body'];
        $this->assertEquals('You have 2 non-seeded new uploads to rescue', $pm->subject(), 'never-message-2');
        $this->assertStringContainsString("Dear {$this->userList[0]->username()}", $body, 'never-body-2-dear');
        $this->assertStringContainsString("[pl]{$this->torrentList[0]->id()}[/pl]", $body, 'never-body-2-pl-0');
        $this->assertStringContainsString("[pl]{$this->torrentList[1]->id()}[/pl]", $body, 'never-body-2-pl-1');
        $pm->remove();

        // reseed one of the torrents by the uploader
        $this->generateReseed($this->torrentList[0], $this->torrentList[0]->uploader());
        $this->torrentList[1]->setUpdate('Time', date('Y-m-d H:i:s'))->modify();

        // reset the time of the remaing never seeded alert back in time to hit
        // the final timeout.
        // reminder: once a torrent is referenced in the torrent_unseeded table,
        // we no longer need to consider the torrent creation date.
        $this->modifyUnseededInterval($this->torrentList[1], NOTIFY_NEVER_SEEDED_FINAL_HOUR + 1);

        $this->assertEquals(0, $inbox->messageTotal(), 'never-message-final-none');
        $neverFinal = $reaper->finalNeverSeededList();
        $this->assertCount(1, $neverFinal, 'never-final-0'); // one user ...
        $this->assertEquals(1,                               // ... with one upload
            $reaper->process(
                $neverFinal,
                Gazelle\Torrent\ReaperState::NEVER,
                Gazelle\Torrent\ReaperNotify::FINAL
            ),
            'never-final-process'
        );
        $this->assertEquals(
            [
                "never_seeded_initial" => $initialUnseededStats['never_seeded_initial'] + 1,
                "never_seeded_final"   => $initialUnseededStats['never_seeded_final'] + 1,
                "unseeded_initial"     => 0,
                "unseeded_final"       => 0,
            ],
            $reaper->stats(),
            'reaper-unseeded-stats-final'
        );

        $this->assertEquals(1, $inbox->messageTotal(), 'never-message-final-count');
        $pm   = $inbox->messageList($pmMan, 1, 0)[0];
        $body = $pm->postList(1, 0)[0]['body'];
        $this->assertEquals('You have a non-seeded new upload scheduled for deletion very soon', $pm->subject(), 'never-message-final-subject');
        $this->assertStringContainsString("Dear {$this->userList[0]->username()}", $body, 'never-body-3-dear');
        $this->assertStringContainsString("[pl]{$this->torrentList[1]->id()}[/pl]", $body, 'never-body-3-pl-1');
        $pm->remove();

        $this->modifyUnseededInterval($this->torrentList[1], REMOVE_NEVER_SEEDED_HOUR + 1);
        $id = $this->torrentList[1]->id();
        $list = $reaper->reaperList(
            state:    Gazelle\Torrent\ReaperState::NEVER,
            interval: REMOVE_NEVER_SEEDED_HOUR,
        );
        $this->assertCount(1, $list, 'never-reap-list');

        $this->assertEquals(1, $reaper->removeNeverSeeded(), 'never-reap-remove');
        $pm   = $inbox->messageList($pmMan, 1, 0)[0];
        $body = $pm->postList(1, 0)[0]['body'];
        $this->assertEquals('1 of your uploads has been deleted for inactivity (never seeded)', $pm->subject(), 'never-remove-message');
        $this->assertStringContainsString("Dear {$this->userList[0]->username()}", $body, 'never-remove-body-dear');
        $this->assertStringContainsString("[url=torrents.php?id={$this->torrentList[1]->group()->id()}]", $body, 'never-remove-body-pl');
        $pm->remove();

        $deleted = $torMan->findById($id);
        $this->assertNull($deleted, 'never-was-reaped');
    }

    public function testUnseeded(): void {
        $torMan  = new Gazelle\Manager\Torrent;
        $userMan = new Gazelle\Manager\User;
        $reaper  = new \Gazelle\Torrent\Reaper($torMan, $userMan);

        $initialUnseededStats = $reaper->stats();
        $unseededInitial = $reaper->initialUnseededList();
        $this->assertCount(0, $unseededInitial, 'unseeded-initial-0-count');

        // reset the last action and time of the unseeded alert back in time to hit the initial timeout
        foreach ($this->torrentList as $torrent) {
            $hour = NOTIFY_UNSEEDED_INITIAL_HOUR + 1;
            $torrent->setUpdate('Time', date('Y-m-d H:i:s', strtotime("-{$hour} hours")))->modify();
            $this->modifyLastAction($torrent, NOTIFY_UNSEEDED_INITIAL_HOUR + 2);
            // pretend they were snatched
            foreach ($this->userList as $user) {
                $this->generateSnatch($torrent, $user);
            }
        }

        // look for unseeded
        $unseededInitial = $reaper->initialUnseededList();
        $this->assertCount(1, $unseededInitial, 'unseeded-initial-1');
        $this->assertEquals(
            2,
            $reaper->process(
                $unseededInitial,
                Gazelle\Torrent\ReaperState::UNSEEDED,
                Gazelle\Torrent\ReaperNotify::INITIAL
            ),
            'unseeded-initial-process'
        );
        $this->assertEquals(
            [
                "never_seeded_initial" => 0,
                "never_seeded_final"   => 0,
                "unseeded_initial"     => $initialUnseededStats['unseeded_seeded_initial'] + 2,
                "unseeded_final"       => 0,
            ],
            $reaper->stats(),
            'reaper-unseeded-stats-0'
        );

        $inboxList = [
            new Gazelle\User\Inbox($this->userList[0]),
            new Gazelle\User\Inbox($this->userList[1]),
        ];
        $pmMan = [
            new Gazelle\Manager\PM($this->userList[0]),
            new Gazelle\Manager\PM($this->userList[1]),
        ];

        $this->assertEquals(1, $inboxList[0]->messageTotal(), 'unseeded-initial-0-inbox');
        $pm   = $inboxList[0]->messageList($pmMan[0], 1, 0)[0];
        $body = $pm->postList(1, 0)[0]['body'];
        $this->assertEquals('There are 2 unseeded uploads to rescue', $pm->subject(), 'unseeded-initial-0-rescue');
        $this->assertStringContainsString("You have 2 uploads that are not currently seeding by you (or anyone else)", $body, 'unseeded-initial-0-body');
        $this->assertStringContainsString("[pl]{$this->torrentList[0]->id()}[/pl]", $body, 'unseeded-initial-0-body-pl-0');
        $this->assertStringContainsString("[pl]{$this->torrentList[1]->id()}[/pl]", $body, 'unseeded-initial-0-body-pl-1');
        $pm->remove();

        $this->assertEquals(1, $inboxList[1]->messageTotal(), 'unseeded-initial-1-inbox');
        $pm   = $inboxList[1]->messageList($pmMan[1], 1, 0)[0];
        $body = $pm->postList(1, 0)[0]['body'];
        $this->assertEquals('You have 2 unseeded snatches to save', $pm->subject(), 'unseeded-initial-1-rescue');
        $this->assertStringContainsString("In the past, you snatched 2 uploads that are no longer being seeded", $body, 'unseeded-initial-1-body');
        $this->assertStringContainsString("[pl]{$this->torrentList[0]->id()}[/pl]", $body, 'unseeded-initial-1-body-pl-0');
        $this->assertStringContainsString("[pl]{$this->torrentList[1]->id()}[/pl]", $body, 'unseeded-initial-1-body-pl-1');
        $pm->remove();

        $initialClaim = $reaper->claimStats();
        $this->assertEquals(['open', 'claimed'], array_keys($initialClaim), 'claim-initial');
        $this->assertEquals(2, $initialClaim['open'], 'claim-open');
        $this->assertEquals(0, $initialClaim['claimed'], 'claim-claimed');

        // snatcher reseeds the first upload
        $this->modifyUnseededInterval($this->torrentList[0], NOTIFY_UNSEEDED_INITIAL_HOUR + 3);
        $this->generateReseed($this->torrentList[0], $this->userList[1]);

        // and wins the glory
        $bonus = $this->userList[1]->bonusPointsTotal();
        $win   = $reaper->claim();
        $this->assertCount(1, $win, 'unseeded-claim-win-count');
        [$torrentId, $userId, $bp] = $win[0];
        $this->assertEquals($this->torrentList[0]->id(), $torrentId, 'unseeded-claim-win-torrent');
        $this->assertEquals($this->userList[1]->id(), $userId, 'unseeded-claim-win-user');
        $this->assertEquals($this->userList[1]->flush()->bonusPointsTotal(), $bonus + $bp, 'unseeded-claim-win-bp');

        // message
        $pm   = $inboxList[1]->messageList($pmMan[1], 1, 0)[0];
        $body = $pm->postList(1, 0)[0]['body'];
        $this->assertEquals("Thank you for reseeding {$this->torrentList[0]->group()->name()}!", $pm->subject(), 'unseeded-initial-1-thx');
        $this->assertStringContainsString("[pl]{$this->torrentList[0]->id()}[/pl]", $body, 'unseeded-initial-1-body-pl-thx');
        $this->assertStringContainsString("$bp bonus points", $body, 'unseeded-initial-1-body-bp-thx');
        $pm->remove();

        $newClaim = $reaper->claimStats();
        $this->assertEquals(1, $newClaim['open'], 'claim-new-open');
        $this->assertEquals(1, $newClaim['claimed'], 'claim-new-claimed');

        // reset the time of the remaing unseeded alert back in time to hit the final timeout.
        $this->modifyLastAction($this->torrentList[1], NOTIFY_UNSEEDED_FINAL_HOUR + 2);
        $this->modifyUnseededInterval($this->torrentList[1], NOTIFY_UNSEEDED_FINAL_HOUR + 1);

        $unseededStats = $reaper->stats();
        $unseededFinal = $reaper->finalUnseededList();

        $this->assertCount(1, $unseededFinal, 'unseeded-final-0');
        $this->assertEquals(1,
            $reaper->process(
                $unseededFinal,
                Gazelle\Torrent\ReaperState::UNSEEDED,
                Gazelle\Torrent\ReaperNotify::FINAL
            ),
            'unseeded-final-process'
        );
        $this->assertEquals(
            [
                "never_seeded_initial" => 0,
                "never_seeded_final"   => 0,
                "unseeded_initial"     => 0,
                "unseeded_final"       => $unseededStats['unseeded_final'] + 1,
            ],
            $reaper->stats(),
            'reaper-unseeded-stats-final'
        );

        $this->assertEquals(1, $inboxList[0]->messageTotal(), 'unseeded-final-0-inbox');
        $pm   = $inboxList[0]->messageList($pmMan[0], 1, 0)[0];
        $body = $pm->postList(1, 0)[0]['body'];
        $this->assertEquals('There is an unseeded upload scheduled for deletion very soon', $pm->subject(), 'unseeded-final-0-rescue');
        $this->assertStringContainsString("You have an upload that is still not currently seeding by you (or anyone else)", $body, 'unseeded-final-0-body');
        $this->assertStringContainsString("[pl]{$this->torrentList[1]->id()}[/pl]", $body, 'unseeded-final-0-body-pl-1');
        $pm->remove();

        $this->assertEquals(0, $inboxList[1]->messageTotal(), 'unseeded-final-no-snatcher-inbox');

        // too late
        $this->modifyUnseededInterval($this->torrentList[1], REMOVE_UNSEEDED_HOUR + 1);
        $id = $this->torrentList[1]->id();
        $list = $reaper->reaperList(
            state:    Gazelle\Torrent\ReaperState::UNSEEDED,
            interval: REMOVE_UNSEEDED_HOUR,
        );
        $this->assertCount(1, $list, 'unseeded-reap-list');
        $this->assertEquals(1, $reaper->removeUnseeded(), 'unseeded-reap-remove');

        $pm   = $inboxList[0]->messageList($pmMan[0], 1, 0)[0];
        $body = $pm->postList(1, 0)[0]['body'];
        $this->assertEquals('1 of your uploads has been deleted for inactivity (unseeded)', $pm->subject(), 'never-remove-message');
        $this->assertStringContainsString("Dear {$this->userList[0]->username()}", $body, 'never-remove-body-dear');
        $this->assertStringContainsString("[url=torrents.php?id={$this->torrentList[1]->group()->id()}]", $body, 'never-remove-body-pl');
        $pm->remove();

        $pm   = $inboxList[1]->messageList($pmMan[1], 1, 0)[0];
        $body = $pm->postList(1, 0)[0]['body'];
        $this->assertEquals('1 of your snatches was deleted for inactivity', $pm->subject(), 'never-remove-snatcher-message');
        $this->assertStringContainsString("Dear {$this->userList[1]->username()}", $body, 'never-remove-snatcher-body-dear');
        $this->assertStringContainsString("[url=torrents.php?id={$this->torrentList[1]->group()->id()}]", $body, 'never-remove-snatcher-body-pl');
        $pm->remove();

        $deleted = $torMan->findById($id);
        $this->assertNull($deleted, 'unseeded-was-reaped');
    }

    public function testNeverNotify(): void {
        // like never, but checking that people who have asked not to receive notifications, do not.
        $this->userList[0]->toggleAttr('no-pm-unseeded-upload', true);
        $this->userList[1]->toggleAttr('no-pm-unseeded-snatch', true);

        $this->assertTrue($this->userList[0]->hasAttr('no-pm-unseeded-upload'), 'reaper-no-notify-upload');
        $this->assertTrue($this->userList[1]->hasAttr('no-pm-unseeded-snatch'), 'reaper-no-notify-snatch');

        // reset the last action and time of the unseeded alert back in time to hit the initial timeout
        foreach ($this->torrentList as $torrent) {
            $hour = NOTIFY_NEVER_SEEDED_INITIAL_HOUR + 1;
            $torrent->setUpdate('Time', date('Y-m-d H:i:s', strtotime("-{$hour} hours")))->modify();
            $this->modifyLastAction($torrent, NOTIFY_NEVER_SEEDED_INITIAL_HOUR + 2);
        }

        // look for never seeded
        $reaper = new \Gazelle\Torrent\Reaper(new Gazelle\Manager\Torrent, new Gazelle\Manager\User);
        $reaper->process(
            $reaper->initialNeverSeededList(),
            Gazelle\Torrent\ReaperState::NEVER,
            Gazelle\Torrent\ReaperNotify::INITIAL
        );

        $this->assertEquals(0, (new Gazelle\User\Inbox($this->userList[0]))->messageTotal(), 'never-uploader-no-notify');
        $this->assertEquals(0, (new Gazelle\User\Inbox($this->userList[1]))->messageTotal(), 'never-snatcher-no-notify');
    }

    public function testUnseededNotify(): void {
        // like unseeded, but checking that people who have asked not to receive notifications, do not.
        $this->userList[0]->toggleAttr('no-pm-unseeded-upload', true);
        $this->userList[1]->toggleAttr('no-pm-unseeded-snatch', true);

        $this->assertTrue($this->userList[0]->hasAttr('no-pm-unseeded-upload'), 'reaper-no-notify-upload');
        $this->assertTrue($this->userList[1]->hasAttr('no-pm-unseeded-snatch'), 'reaper-no-notify-snatch');

        // reset the last action and time of the unseeded alert back in time to hit the initial timeout
        foreach ($this->torrentList as $torrent) {
            $hour = NOTIFY_UNSEEDED_INITIAL_HOUR + 1;
            $torrent->setUpdate('Time', date('Y-m-d H:i:s', strtotime("-{$hour} hours")))->modify();
            $this->modifyLastAction($torrent, NOTIFY_UNSEEDED_INITIAL_HOUR + 2);
            $this->generateSnatch($torrent, $this->userList[1]);
        }

        // look for unseeded
        $reaper = new \Gazelle\Torrent\Reaper(new Gazelle\Manager\Torrent, new Gazelle\Manager\User);
        $reaper->process(
            $reaper->initialUnseededList(),
            Gazelle\Torrent\ReaperState::UNSEEDED,
            Gazelle\Torrent\ReaperNotify::INITIAL
        );

        $this->assertEquals(1, (new Gazelle\User\Inbox($this->userList[0]))->messageTotal(), 'unseeded-uploader-no-notify');
        $this->assertEquals(0, (new Gazelle\User\Inbox($this->userList[1]))->messageTotal(), 'unseeded-snatcher-no-notify');

        $timeline = $reaper->timeline();
        $this->assertEquals([2], array_values($timeline), 'reaper-two-today');
        $today = current(array_keys($timeline));
        // take into account the chance of running a few seconds before midnight
        $this->assertContains($today, [date('Y-m-d'), date('Y-m-d', strtotime('1 hour ago'))], 'reaper-timeline');
    }

}
