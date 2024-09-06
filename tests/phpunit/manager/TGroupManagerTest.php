<?php

namespace Gazelle;

use PHPUnit\Framework\TestCase;

class TGroupManagerTest extends TestCase {
    protected array         $tgroupList = [];
    protected array         $torrentList = [];
    protected User $user;

    public function setUp(): void {
        $this->user = \GazelleUnitTest\Helper::makeUser('tgman.' . randomString(10), 'tgman');
        $this->tgroupList = [
            \GazelleUnitTest\Helper::makeTGroupMusic(
                name:       'phpunit tgman ' . randomString(6),
                artistName: [[ARTIST_MAIN], ['phpunit tgman ' . randomString(12)]],
                tagName:    ['folk'],
                user:       $this->user,
            ),
        ];
        $this->torrentList = [
            \GazelleUnitTest\Helper::makeTorrentMusic(
                tgroup: $this->tgroupList[0],
                user:  $this->user,
                title: randomString(10),
            ),
        ];
    }

    public function tearDown(): void {
        foreach ($this->torrentList as $torrent) {
            \GazelleUnitTest\Helper::removeTGroup($torrent->group(), $this->user);
        }
        $this->user->remove();
    }

    public function testFindByTorrentId(): void {
        $manager = new Manager\TGroup();
        $torrentId = $this->torrentList[0]->id();
        $this->assertEquals(
            $this->tgroupList[0]->id(),
            $manager->findByTorrentId($torrentId)->id(),
            'tgman-find-by-torrent-id'
        );
    }

    public function testFindByInfohash(): void {
        $manager = new Manager\TGroup();
        $infohash = $this->torrentList[0]->infohash();
        $this->assertEquals(
            $this->tgroupList[0]->id(),
            $manager->findByTorrentInfohash($infohash)->id(),
            'tgman-find-by-torrent-id'
        );
    }

    public function testRefreshBetterTranscode(): void {
        $manager = new Manager\TGroup();
        // There is at least something from our own tgroup
        $this->assertGreaterThanOrEqual(1, $manager->refreshBetterTranscode(), 'tgman-refresh-better-transcode');
    }
}
