<?php

use \PHPUnit\Framework\TestCase;

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
}
