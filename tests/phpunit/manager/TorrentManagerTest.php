<?php

use \PHPUnit\Framework\TestCase;

use \Gazelle\Enum\LeechType;
use \Gazelle\Enum\LeechReason;

require_once(__DIR__ . '/../../../lib/bootstrap.php');
require_once(__DIR__ . '/../../helper.php');

class TorrentManagerTest extends TestCase {
    protected array         $torrentList = [];
    protected array         $topTenList = [];
    protected \Gazelle\User $user;

    public function setUp(): void {
        $this->user = Helper::makeUser('torman.' . randomString(10), 'torman');
        $this->user->setField('Enabled', '1')->modify();
        $this->torrentList = [
            Helper::makeTorrentMusic(
                tgroup: Helper::makeTGroupMusic(
                    name:       'phpunit torman ' . randomString(6),
                    artistName: [[ARTIST_MAIN], ['phpunit torman ' . randomString(12)]],
                    tagName:    ['folk'],
                    user:       $this->user,
                ),
                user:  $this->user,
                title: randomString(10),
            ),
            Helper::makeTorrentMusic(
                tgroup: Helper::makeTGroupMusic(
                    name:       'phpunit torman ' . randomString(6),
                    artistName: [[ARTIST_MAIN], ['phpunit torman ' . randomString(12)]],
                    tagName:    ['funk'],
                    user:       $this->user,
                ),
                user:  $this->user,
                title: randomString(10),
            )
        ];

        // alter the created dates so that each torrent is created on a different day
        // first torrent is two days ago, second torrent is yesterday
        $day = 2;
        foreach ($this->torrentList as $torrent) {
            $created = (new DateTime($torrent->created()))
               ->sub(new DateInterval("P{$day}D"))->format('Y-m-d H:i:s');
            $torrent->setField('created', $created)->modify();
            $day--;
        }
    }

    public function tearDown(): void {
        $db = Gazelle\DB::DB();
        foreach ($this->topTenList as $id) {
            $db->prepared_query("
                DELETE FROM top10_history_torrents WHERE HistoryID = ?
                ", $id
            );
            $db->prepared_query("
                DELETE FROM top10_history WHERE ID = ?
                ", $id
            );
        }
        foreach ($this->torrentList as $torrent) {
            Helper::removeTGroup($torrent->group(), $this->user);
        }
        $this->user->remove();
    }

    public function testLatestUploads(): void {
        $manager = new \Gazelle\Manager\Torrent;
        $list = $manager->latestUploads(5);
        $latestTotal = count($list);
        $this->assertGreaterThanOrEqual(2, $latestTotal, 'latest-uploads-two-plus');
        $remove = array_shift($this->torrentList);
        Helper::removeTGroup($remove->group(), $this->user);
        $this->assertEquals(
            $latestTotal - 1,
            count($manager->latestUploads(5)),
            'latest-uploads-after-remove'
        );
    }

    public function testTopTenHistoryList(): void {
        // add torrents to history top ten daily histories
        $db = Gazelle\DB::DB();
        foreach ($this->torrentList as $torrent) {
            $db->prepared_query("
                INSERT INTO top10_history (Date, Type) VALUES (date(?), ?)
                ", $torrent->created(), 'Daily'
            );
            $histId = $db->inserted_id();

            $db->prepared_query("
                INSERT INTO top10_history_torrents
                       (TorrentID, HistoryID, sequence)
                VALUES (?,         ?,         ?)
                ", $torrent->id(), $histId, 1
            );
            $this->topTenList[] = $histId;
        }

        // now add them to a top ten weekly history
        $db->prepared_query("
            INSERT INTO top10_history (Date, Type) VALUES (date(?), ?)
            ", $this->torrentList[0]->created(), 'Weekly'
        );
        $histId   = $db->inserted_id();
        $sequence = 0;
        foreach ($this->torrentList as $torrent) {
            $db->prepared_query("
                INSERT INTO top10_history_torrents
                       (TorrentID, HistoryID, sequence)
                VALUES (?,         ?,         ?)
                ", $torrent->id(), $histId, ++$sequence
            );
        }
        $this->topTenList[] = $histId;

        $manager = new \Gazelle\Manager\Torrent;
        $date = explode(' ', $this->torrentList[0]->created())[0];
        $list = $manager->topTenHistoryList($date, isByDay: true);
        $this->assertCount(1, $list, 'tor-top10-history-by-day');
        $this->assertEquals(1, $list[0]['sequence'], 'tor-top10-day-1-sequence');
        $this->assertEquals($this->torrentList[0]->id(), $list[0]['torrent_id'], 'tor-top10-day-1-sequence');

        $date = explode(' ', $this->torrentList[1]->created())[0];
        $list = $manager->topTenHistoryList($date, isByDay: true);
        $this->assertEquals(1, $list[0]['sequence'], 'tor-top10-day-2-sequence');
        $this->assertEquals($this->torrentList[1]->id(), $list[0]['torrent_id'], 'tor-top10-day-2-sequence');

        $date = explode(' ', $this->torrentList[0]->created())[0];
        $list = $manager->topTenHistoryList($date, isByDay: false);
        $this->assertCount(2, $list, 'tor-top10-history-by-week');
        $this->assertEquals(
            array_map(fn($t) => $t->id(), $this->torrentList),
            array_map(fn($t) => $t['torrent_id'], $list),
            'tor-top10-history-week-list'
        );
    }

    public function testReseedGracePeriod(): void {
        $db = Gazelle\DB::DB();

        // Torrent created today never active
        $this->assertFalse($this->torrentList[1]->isReseedRequestAllowed());

        // Torrent created RESEED_NEVER_ACTIVE_TORRENT days ago never active
        $created = (new DateTime())
            ->sub(new DateInterval("P3D"))->format('Y-m-d H:i:s');
        $this->torrentList[1]->setField('created', $created)->modify();
        $this->assertTrue($this->torrentList[1]->isReseedRequestAllowed());

        // Torrent created RESEED_NEVER_ACTIVE_TORRENT days ago has been active recently
        $db->prepared_query("
            UPDATE torrents_leech_stats SET last_action = now() WHERE TorrentID = ?
        ", $this->torrentList[1]->id());
        $this->torrentList[1]->flush();
        $this->assertFalse($this->torrentList[1]->isReseedRequestAllowed());

        // Torrent created RESEED_NEVER_ACTIVE_TORRENT days ago reseed request
        $db->prepared_query("
            UPDATE torrents_leech_stats SET last_action = NULL WHERE TorrentID = ?
        ", $this->torrentList[1]->id());
        $this->torrentList[1]->flush();
        $this->torrentList[1]->setField('LastReseedRequest', date('Y-m-d H:i:s'))->modify();
        $this->assertFalse($this->torrentList[1]->isReseedRequestAllowed());
        $this->torrentList[1]->setField('LastReseedRequest', null)->modify();

        // Torrent was active RESEED_TORRENT days ago
        $lastActive = (new DateTime($created))
            ->sub(new DateInterval("P15D"))->format('Y-m-d H:i:s');
        $db->prepared_query("
            UPDATE torrents_leech_stats SET last_action = ? WHERE TorrentID = ?
        ", $lastActive, $this->torrentList[1]->id());
        $this->torrentList[1]->setField('created', $created)->modify();
        $this->assertTrue($this->torrentList[1]->isReseedRequestAllowed());

        // Torrent was active RESEED_TORRENT days ago reseed request
        $this->torrentList[1]->setField('LastReseedRequest', date('Y-m-d H:i:s'))->modify();
        $this->assertFalse($this->torrentList[1]->isReseedRequestAllowed());
    }

    public function testTorrentLeechReason(): void {
        $manager = new \Gazelle\Manager\Torrent;

        $this->assertEquals(LeechReason::Normal,    $manager->lookupLeechReason('0'), 'torman-leechreason-normal');
        $this->assertEquals(LeechReason::StaffPick, $manager->lookupLeechReason('1'), 'torman-leechreason-staffpick');
        $this->assertEquals(LeechReason::Permanent, $manager->lookupLeechReason('2'), 'torman-leechreason-permanent');
        $this->assertEquals(LeechReason::Showcase,  $manager->lookupLeechReason('3'), 'torman-leechreason-showcase');

        $showcase = LeechReason::Showcase;
        $this->assertEquals('Showcase', $showcase->label(), 'torman-leechreason-value-showcase');

        $this->assertCount(5, $manager->leechReasonList(), 'torman-leechreason-list');
    }

    public function testTorrentLeechType(): void {
        $manager = new \Gazelle\Manager\Torrent;

        $this->assertEquals(LeechType::Normal,  $manager->lookupLeechType('0'), 'torman-leechtype-normal');
        $this->assertEquals(LeechType::Free,    $manager->lookupLeechType('1'), 'torman-leechtype-free');
        $this->assertEquals(LeechType::Neutral, $manager->lookupLeechType('2'), 'torman-leechtype-neutral');

        $leechType = LeechType::Free;
        $this->assertEquals('Freeleech', $leechType->label(), 'torman-leechtype-value-free');

        $this->assertCount(3, $manager->leechTypeList(), 'torman-leechtype-list');
    }
}
