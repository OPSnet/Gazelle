<?php

use PHPUnit\Framework\TestCase;
use Gazelle\Enum\UserTorrentSearch;

require_once(__DIR__ . '/../../../lib/bootstrap.php');
require_once(__DIR__ . '/../../helper.php');

class UserTorrentTest extends TestCase {
    protected array $torrentList;
    protected \Gazelle\User $user;

    public function setUp(): void {
        $this->user    = Helper::makeUser('torrent.' . randomString(10), 'search');
        $this->torrentList = [
            Helper::makeTorrentMusic(
                tgroup: Helper::makeTGroupMusic(
                    name:       'phpunit torrent ' . randomString(6),
                    artistName: [[ARTIST_MAIN], ['phpunit torrent ' . randomString(12)]],
                    tagName:    ['pop'],
                    user:       $this->user,
                ),
                user:  $this->user,
                title: randomString(10),
            ),
            Helper::makeTorrentMusic(
                tgroup: Helper::makeTGroupMusic(
                    name:       'phpunit torrent ' . randomString(6),
                    artistName: [[ARTIST_MAIN], ['phpunit torrent ' . randomString(12)]],
                    tagName:    ['bop'],
                    user:       $this->user,
                ),
                user:  $this->user,
                title: randomString(10),
            )
        ];
    }

    public function tearDown(): void {
        foreach ($this->torrentList as $t) {
            Helper::removeTGroup($t->group(), $this->user);
        }
        $this->user->remove();
    }

    public function testSeeding(): void {
        Helper::generateTorrentSeed($this->torrentList[1], $this->user);
        $search = new Gazelle\Search\UserTorrent($this->user, UserTorrentSearch::seeding);
        $this->assertEquals('seeding', $search->label(), 'search-utor-label-seeding');
        $this->assertEquals(
            [$this->torrentList[1]->id()],
            $search->idList(),
            'search-utor-list-seeding'
        );
    }

    public function testSnatched(): void {
        Helper::generateTorrentSnatch($this->torrentList[0], $this->user);
        $search = new Gazelle\Search\UserTorrent($this->user, UserTorrentSearch::snatched);
        $this->assertEquals('snatched', $search->label(), 'search-utor-label-snatched');
        $this->assertEquals(
            [$this->torrentList[0]->id()],
            $search->idList(),
            'search-utor-list-snatched'
        );
    }

    public function testUploaded(): void {
        $search = new Gazelle\Search\UserTorrent($this->user, UserTorrentSearch::uploaded);
        $this->assertEquals('uploaded', $search->label(), 'search-utor-label-uploaded');
        $this->assertEquals(
            array_map(fn ($t) => $t->id(), $this->torrentList),
            $search->idList(),
            'search-utor-list-uploaded'
        );
    }
}
