<?php

use \PHPUnit\Framework\TestCase;

require_once(__DIR__ . '/../../../lib/bootstrap.php');
require_once(__DIR__ . '/../../helper.php');

class TGroupManagerTest extends TestCase {
    protected array         $tgroupList = [];
    protected array         $torrentList = [];
    protected \Gazelle\User $user;

    public function setUp(): void {
        $this->user = Helper::makeUser('tgman.' . randomString(10), 'tgman');
        $this->tgroupList = [
            Helper::makeTGroupMusic(
                name:       'phpunit tgman ' . randomString(6),
                artistName: [[ARTIST_MAIN], ['phpunit tgman ' . randomString(12)]],
                tagName:    ['folk'],
                user:       $this->user,
            ),
        ];
        $this->torrentList = [
            Helper::makeTorrentMusic(
                tgroup: $this->tgroupList[0],
                user:  $this->user,
                title: randomString(10),
            ),
        ];
    }

    public function tearDown(): void {
        foreach ($this->torrentList as $torrent) {
            Helper::removeTGroup($torrent->group(), $this->user);
        }
        $this->user->remove();
    }

    public function testFindByTorrentId(): void {
        $manager = new \Gazelle\Manager\TGroup;
        $torrentId = $this->torrentList[0]->id();
        $this->assertEquals(
            $this->tgroupList[0]->id(),
            $manager->findByTorrentId($torrentId)->id(),
            'tgman-find-by-torrent-id'
        );
    }

    public function testFindByInfohash(): void {
        $manager = new \Gazelle\Manager\TGroup;
        $infohash = $this->torrentList[0]->infohash();
        $this->assertEquals(
            $this->tgroupList[0]->id(),
            $manager->findByTorrentInfohash($infohash)->id(),
            'tgman-find-by-torrent-id'
        );
    }

    public function testRefreshBetterTranscode(): void {
        $manager = new \Gazelle\Manager\TGroup;
        // There is at least something from our own tgroup
        $this->assertGreaterThanOrEqual(1, $manager->refreshBetterTranscode(), 'tgman-refresh-better-transcode');
    }
}
