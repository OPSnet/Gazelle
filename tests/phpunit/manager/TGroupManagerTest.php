<?php

namespace Gazelle;

use PHPUnit\Framework\TestCase;
use GazelleUnitTest\Helper;

class TGroupManagerTest extends TestCase {
    protected array $tgroupList;
    protected array $torrentList;
    protected User  $user;

    public function setUp(): void {
        $this->user = Helper::makeUser('tgman.' . randomString(10), 'tgman');
        $this->user->requestContext()->setViewer($this->user);
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
        Helper::removeUser($this->user);
    }

    public function testFindByTorrentId(): void {
        $manager = new Manager\TGroup();
        $torrentId = $this->torrentList[0]->id;
        $this->assertInstanceOf(
            TGroup::class,
            $manager->findByTorrentId($torrentId),
            'tgman-find-by-torrent-id'
        );
    }

    public function testFindByInfohash(): void {
        $manager = new Manager\TGroup();
        $infohash = $this->torrentList[0]->infohash();
        $this->assertInstanceOf(
            TGroup::class,
            $manager->findByTorrentInfohash($infohash),
            'tgman-find-by-torrent-id'
        );
    }

    public function testFindByGroupName(): void {
        $manager = new Manager\TGroup();
        $gid = $this->torrentList[0]->groupId();
        $idList = $manager->findByGroupName($this->torrentList[0]->group()->name());
        $this->assertSame([$gid], $idList, 'tgman-find-by-group-name');
    }

    public function testRefreshBetterTranscode(): void {
        $manager = new Manager\TGroup();
        // There is at least something from our own tgroup
        $this->assertGreaterThanOrEqual(1, $manager->refreshBetter(), 'tgman-refresh-better-transcode');
    }

    public function testJsonTGroupList(): void {
        $tgroup = $this->tgroupList[0];
        $ungrouped = new Json\TGroupList(
                new User\Bookmark($this->user),
                $this->user->snatch(),
                [$tgroup->torrentIdList()[0] => $tgroup->id],
                false,
                2,
                1,
            )->payload();
        $this->assertEqualsCanonicalizing(
            [
                'currentPage',
                'pages',
                'results',
            ],
            array_keys($ungrouped),
            'json-tgroup-ungrouped-list',
        );
        $result = $ungrouped['results'][0];
        $this->assertSame(
            $tgroup->id,
            $result['groupId'],
            'json-tgroup-ungrouped-id',
        );
        $this->assertSame(
            $tgroup->torrentIdList()[0],
            $result['torrentId'],
            'json-tgroup-ungrouped-torrentid',
        );
        $grouped = new Json\TGroupList(
                new User\Bookmark($this->user),
                $this->user->snatch(),
                [$tgroup->torrentIdList()[0] => $tgroup->id],
                true,
                2,
                1,
            )->payload();
        $this->assertEqualsCanonicalizing(
            [
                'currentPage',
                'pages',
                'results',
            ],
            array_keys($grouped),
            'json-tgroup-grouped-list',
        );
        $result = $grouped['results'][0];
        $this->assertSame(
            $tgroup->id,
            $result['groupId'],
            'json-tgroup-grouped-id'
        );
        $this->assertSame(
            $tgroup->torrentIdList()[0],
            $result['torrents'][0]['torrentId'],
            'json-tgroup-grouped-torrentid',
        );
    }

    public function testTGroupBookmarkEditor(): void {
        $this->tgroupList[] = Helper::makeTGroupMusic(
            name:       'phpunit tgman ' . randomString(6),
            artistName: [[ARTIST_MAIN], ['phpunit tgman ' . randomString(12)]],
            tagName:    ['neo.folk'],
            user:       $this->user,
        );
        $editor = new Editor\UserBookmark($this->user->id);
        $this->assertSame(
            0,
            $editor->modify([]),
            'tgroup-bookmark-editor-no-modify',
        );
        $this->assertSame(
            2,
            $editor->modify([
                $this->tgroupList[0]->id => 10,
                $this->tgroupList[1]->id => 20,
            ]),
            'tgroup-bookmark-editor-modify',
        );
        $this->assertSame(
            1,
            $editor->remove([
                $this->tgroupList[1]->id,
            ]),
            'tgroup-bookmark-editor-remove',
        );
    }
}
