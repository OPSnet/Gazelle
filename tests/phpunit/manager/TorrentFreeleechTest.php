<?php

use PHPUnit\Framework\TestCase;

require_once(__DIR__ . '/../../../lib/bootstrap.php');
require_once(__DIR__ . '/../../helper.php');

use Gazelle\Enum\LeechType;
use Gazelle\Enum\LeechReason;

class TorrentFreeleechTest extends TestCase {
    protected array $torrentList;

    public function setUp(): void {
        $user = Helper::makeUser('torman.' . randomString(10), 'torrent.manager');
        $tgroup = Helper::makeTGroupMusic(
            name:       'phpunit torman ' . randomString(6),
            artistName: [[ARTIST_MAIN], ['DJ Torman ' . randomString(12)]],
            tagName:    ['hip.hop'],
            user:       $user,
        );
        $this->torrentList = array_map(fn($info) =>
            Helper::makeTorrentMusic(
                tgroup: $tgroup,
                format: $info['format'],
                size:   $info['size'],
                title:  $info['title'],
                user:   $user,
            ), [
                ['format' => 'FLAC', 'size' => 10_000_000, 'title' => 'Regular Edition'],
                ['format' => 'FLAC', 'size' => 15_000_000, 'title' => 'Limited Edition'],
                ['format' => 'FLAC', 'size' => 20_000_000, 'title' => 'Deluxe Edition'],
                ['format' => 'FLAC', 'size' => 25_000_000, 'title' => 'Ultra Edition'],
                ['format' => 'MP3',  'size' =>  2_000_000, 'title' => 'Ultra Edition'],
            ]
        );
    }

    public function tearDown(): void {
        $tgroup = $this->torrentList[0]->group();
        $user   = $this->torrentList[0]->uploader();
        $torMan = new Gazelle\Manager\Torrent();
        foreach ($this->torrentList as $torrent) {
            $torrent->remove($user, 'torman unit test');
        }
        $tgroup->remove($user);
        $user->remove();
    }

    public function testTorrentFreeleechThreshold(): void {
        $this->assertEquals(
            4,
            $this->torrentList[0]->group()->setFreeleech(
                torMan:    new \Gazelle\Manager\Torrent(),
                tracker:   new \Gazelle\Tracker(),
                user:      $this->torrentList[0]->uploader(),
                leechType: LeechType::Free,
                reason:    LeechReason::StaffPick,
                threshold: 22_000_000,
            ),
            'fl-free-staffpick-22M'
        );

        $this->assertEquals(LeechType::Free,    $this->torrentList[0]->flush()->leechType(), 'torman-0-is-free');
        $this->assertEquals(LeechType::Free,    $this->torrentList[1]->flush()->leechType(), 'torman-1-is-free');
        $this->assertEquals(LeechType::Free,    $this->torrentList[2]->flush()->leechType(), 'torman-2-is-free');
        $this->assertEquals(LeechType::Neutral, $this->torrentList[3]->flush()->leechType(), 'torman-3-is-neutral');
        $this->assertEquals(LeechType::Normal,  $this->torrentList[4]->flush()->leechType(), 'torman-4-is-normal');

        $this->assertEquals(
            4,
            $this->torrentList[0]->group()->setFreeleech(
                torMan:    new \Gazelle\Manager\Torrent(),
                tracker:   new \Gazelle\Tracker(),
                user:      $this->torrentList[0]->uploader(),
                leechType: LeechType::Normal,
                reason:    LeechReason::Normal,
            ),
            'fl-normal'
        );

        $this->assertEquals(LeechType::Normal, $this->torrentList[0]->flush()->leechType(), 'torman-0-is-now-normal');
        $this->assertEquals(LeechType::Normal, $this->torrentList[1]->flush()->leechType(), 'torman-1-is-now-normal');
        $this->assertEquals(LeechType::Normal, $this->torrentList[2]->flush()->leechType(), 'torman-2-is-now-normal');
        $this->assertEquals(LeechType::Normal, $this->torrentList[3]->flush()->leechType(), 'torman-3-is-now-normal');
        $this->assertEquals(LeechType::Normal, $this->torrentList[4]->flush()->leechType(), 'torman-4-is-now-normal');
    }

    public function testTorrentFreeleechAllFree(): void {
        $this->assertEquals(
            5,
            $this->torrentList[0]->group()->setFreeleech(
                torMan:    new \Gazelle\Manager\Torrent(),
                tracker:   new \Gazelle\Tracker(),
                user:      $this->torrentList[0]->uploader(),
                leechType: LeechType::Free,
                reason:    LeechReason::StaffPick,
                all:       true,
            ),
            'fl-free-all'
        );

        $this->assertEquals(LeechType::Free, $this->torrentList[3]->flush()->leechType(), 'torman-3-is-free');
        $this->assertEquals(LeechType::Free, $this->torrentList[4]->flush()->leechType(), 'torman-4-is-free');
    }
}
