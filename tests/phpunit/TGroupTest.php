<?php

namespace Gazelle;

use PHPUnit\Framework\TestCase;

class TGroupTest extends TestCase {
    protected TGroup $tgroup;
    protected TGroup $tgroupExtra;
    protected Manager\TGroup $manager;
    protected string $name;
    protected int $year;
    protected string $recordLabel;
    protected string $catalogueNumber;
    protected array $userList;

    public function setUp(): void {
        $this->userList = [
            'admin' => \GazelleUnitTest\Helper::makeUser('tgroup.a.' . randomString(6), 'tgroup'),
            'user'  => \GazelleUnitTest\Helper::makeUser('tgroup.u.' . randomString(6), 'tgroup'),
            'nope'  => \GazelleUnitTest\Helper::makeUser('tgroup.n.' . randomString(6), 'tgroup'),
        ];
        $this->userList['admin']->setField('PermissionID', MOD)->modify();

        $this->name            = 'phpunit live in ' . randomString(6);
        $this->year            = (int)date('Y');
        $this->recordLabel     = randomString(6) . ' Records';
        $this->catalogueNumber = randomString(3) . '-' . random_int(1000, 2000);
        $this->manager         = new Manager\TGroup();
        $this->tgroup      = $this->manager->create(
            categoryId:      1,
            name:            $this->name,
            year:            $this->year,
            recordLabel:     $this->recordLabel,
            catalogueNumber: $this->catalogueNumber,
            description:     "Description of {$this->name}",
            image:           'https://example.com/' . randomString(10) . '.jpg',
            releaseType:     (new ReleaseType())->findIdByName('Live album'),
            showcase:        false,
        );

        // and add some torrents to the group
        \GazelleUnitTest\Helper::makeTorrentMusic(
            tgroup:          $this->tgroup,
            user:            $this->userList['user'],
            catalogueNumber: 'UA-TG-1',
        );
        \GazelleUnitTest\Helper::makeTorrentMusic(
            tgroup:          $this->tgroup,
            user:            $this->userList['admin'],
            catalogueNumber: 'UA-TG-1',
            format:          'MP3',
            encoding:        'V0',
        );
        \GazelleUnitTest\Helper::makeTorrentMusic(
            tgroup:          $this->tgroup,
            user:            $this->userList['user'],
            catalogueNumber: 'UA-TG-2',
            title:           'Limited Edition',
        );
    }

    public function tearDown(): void {
        if (isset($this->tgroupExtra)) {
            \GazelleUnitTest\Helper::removeTGroup($this->tgroupExtra, $this->userList['admin']);
        }
        \GazelleUnitTest\Helper::removeTGroup($this->tgroup, $this->userList['admin']);
        foreach ($this->userList as $user) {
            $user->remove();
        }
    }

    public function testTGroupCreate(): void {
        $this->assertNotNull($this->tgroup, 'tgroup-create-exists');
        $this->assertGreaterThan(1, $this->tgroup->id(), 'tgroup-create-id');

        $this->assertTrue($this->tgroup->categoryGrouped(), 'tgroup-create-category-grouped');
        $this->assertFalse($this->tgroup->isShowcase(), 'tgroup-create-showcase');
        $this->assertFalse($this->tgroup->hasNoCoverArt(), 'tgroup-create-has-no-cover-art');
        $this->assertCount(0, $this->tgroup->revisionList(), 'tgroup-create-revision-list');
        $this->assertEquals('Music', $this->tgroup->categoryName(), 'tgroup-create-category-name');
        $this->assertEquals('cats_music', $this->tgroup->categoryCss(), 'tgroup-create-category-ss');
        $this->assertEquals('Live album', $this->tgroup->releaseTypeName(), 'tgroup-create-release-type-name');
        $this->assertEquals($this->name, $this->tgroup->name(), 'tgroup-create-name');
        $this->assertEquals($this->year, $this->tgroup->year(), 'tgroup-create-year');
        $this->assertEquals($this->recordLabel, $this->tgroup->recordLabel(), 'tgroup-create-record-label');
        $this->assertEquals($this->catalogueNumber, $this->tgroup->catalogueNumber(), 'tgroup-create-catalogue-number');
        $this->assertEquals(0, $this->tgroup->unresolvedReportsTotal(), 'tgroup-create-unresolved-reports');
        $this->assertEquals($this->tgroup->name(), $this->tgroup->flush()->name(), 'tgroup-create-flush');
        $this->assertStringStartsWith('https://example.com/', $this->tgroup->image(), 'tgroup-create-image');
        $this->assertStringStartsWith('https://example.com/', $this->tgroup->cover(), 'tgroup-create-cover');

        $this->assertTrue($this->tgroup->isOwner($this->userList['user']), 'tgroup-user-is-owner');
        $this->assertTrue($this->tgroup->isOwner($this->userList['admin']), 'tgroup-admin-is-owner');
        $this->assertFalse($this->tgroup->isOwner($this->userList['nope']), 'tgroup-nope-not-owner');

        $this->assertNull($this->manager->findById(-666), 'tgroup-no-instance-of');
        $find = $this->manager->findById($this->tgroup->id());
        $this->assertInstanceOf(TGroup::class, $find, 'tgroup-instance-of');
        $this->assertEquals($this->tgroup->id(), $find->id(), 'tgroup-create-find');

        $torMan = new Manager\Torrent();
        $torrent = $torMan->findById($this->tgroup->torrentIdList()[0]);
        $this->assertEquals(1, $torrent->tokenCount(), 'tgroup-torrent-fl-cost');
        $this->assertFalse($this->userList['user']->canSpendFLToken($torrent), 'tgroup-user-no-fltoken');

        $bonus = (new User\Bonus($this->userList['user']));
        $this->assertEquals(1, $bonus->addPoints(10000), 'tgroup-user-add-bp');
        $this->assertEquals(1, $bonus->purchaseToken('token-1'), 'tgroup-user-buy-token');
        $this->assertTrue($this->userList['user']->canSpendFLToken($torrent), 'tgroup-user-fltoken');

        (new Stats\Users())->refresh();
        $this->assertEquals(2, $this->userList['user']->stats()->uploadTotal(), 'tgroup-user-stats-upload');
        $this->assertEquals(1, $this->userList['user']->stats()->uniqueGroupTotal(), 'tgroup-user-stats-unique');
        $this->assertEquals(1, $this->userList['admin']->stats()->uploadTotal(), 'tgroup-user-admin-upload');
    }

    public function testTGroupArtist(): void {
        $artMan = new Manager\Artist();
        $user   = $this->userList['admin'];
        $artistName = 'phpunit ' . randomString(6) . ' band';
        $this->assertEquals(1, $this->tgroup->addArtists([ARTIST_MAIN], [$artistName], $user, $artMan), 'tgroup-artist-add');
        $this->assertEquals("$artistName – {$this->tgroup->name()} [{$this->tgroup->year()} Live album]", $this->tgroup->text(), 'tgroup-artist-text');

        $this->assertNotNull($this->tgroup->primaryArtist(), 'tgroup-artist-primary');

        $artistRole = $this->tgroup->artistRole();
        $this->assertNotNull($artistRole, 'tgroup-artist-artist-role');

        $idList = $artistRole->idList();
        $this->assertCount(1, $idList, 'tgroup-artist-role-idlist');

        $main = $idList[ARTIST_MAIN];
        $this->assertCount(1, $main, 'tgroup-artist-role-main');

        $first = current($main);
        $this->assertEquals($artistName, $first['name'], 'tgroup-artist-first-name');

        $foundByArtist = $this->manager->findByArtistReleaseYear(
            $this->tgroup->artistRole()->text(),
            $this->tgroup->name(),
            $this->tgroup->releaseType(),
            $this->tgroup->year(),
        );
        $this->assertEquals($this->tgroup->id(), $foundByArtist->id(), 'tgroup-find-name');

        $this->assertEquals(
            2,
            $this->tgroup->addArtists(
                [ARTIST_MAIN,     ARTIST_GUEST],
                ["$artistName-2", "$artistName-guest"],
                $user,
                $artMan,
            ),
            'tgroup-artist-add-2'
        );
        $this->assertEquals(
            [
                ARTIST_MAIN  => [$artistName, "$artistName-2"],
                ARTIST_GUEST => ["$artistName-guest"],
            ],
            $this->tgroup->artistRole()->nameList(),
            'tgroup-artist-name-list'
        );

        /* turn the two Main and Guest into DJs */
        $roleList = $this->tgroup->artistRole()->roleList();
        $roleAliasList = [
            ...array_map(fn ($artist) => [ARTIST_MAIN, $artist['aliasid']], $roleList['main']),
            ...array_map(fn ($artist) => [ARTIST_GUEST, $artist['aliasid']], $roleList['guest']),
        ];
        $this->assertEquals(
            3,
            $this->tgroup->artistRole()->modifyList($roleAliasList, ARTIST_DJ, $user),
            'tgroup-a-dj-saved-my-life'
        );
        $this->assertEquals('Various DJs', $this->tgroup->flush()->artistRole()->text(), 'tgroup-2manydjs');
        $this->assertEquals(
            1,
            $this->tgroup->artistRole()->removeList([[ARTIST_DJ, $roleAliasList[0][1]]], $user),
            'tgroup-hang-the-dj'
        );
        $this->assertEquals(
            "$artistName-2 and $artistName-guest",
            $this->tgroup->flush()->artistRole()->text(),
            'tgroup-dj-final'
        );
    }

    public function testTGroupCoverArt(): void {
        $coverId = $this->tgroup->addCoverArt('https://www.example.com/cover.jpg', 'cover art summary', $this->userList['user']);
        $this->assertGreaterThan(0, $coverId, 'tgroup-cover-art-add');
        $this->assertEquals(1, $this->tgroup->removeCoverArt($coverId, $this->userList['user']), 'tgroup-cover-art-del-ok');
        $this->assertEquals(0, $this->tgroup->removeCoverArt(9999999, $this->userList['user']), 'tgroup-cover-art-del-nok');
    }

    public function testTGroupRevision(): void {
        $revisionId = $this->tgroup->createRevision(
            $this->tgroup->description() . "\nmore text",
            'https://www.example.com/image.jpg',
            'phpunit testTGroup summary',
            $this->userList['admin'],
        );
        $this->assertGreaterThan(0, $revisionId, 'tgroup-revision-add');
    }

    public function testTGroupJson(): void {
        $json = new Json\TGroup(
            $this->tgroup,
            $this->userList['user'],
            new Manager\Torrent(),
        );
        $this->assertIsArray($json->payload());
        ['group' => $group, 'torrents' => $torrentList] = $json->payload();
        $this->assertEquals(
            ["wikiBody", "wikiBBcode", "wikiImage", "proxyImage", "id", "name",
             "year", "recordLabel", "catalogueNumber", "releaseType",
             "releaseTypeName", "categoryId", "categoryName", "time",
             "vanityHouse", "isBookmarked", "tags", "musicInfo"
            ],
            array_keys($group),
            'tgroup-json-group'
        );
        $this->assertCount(3, $torrentList, 'tgroup-json-torrent-total');
        $this->assertEquals(
            ["infoHash", "id", "media", "format", "encoding", "remastered",
             "remasterYear", "remasterTitle", "remasterRecordLabel",
             "remasterCatalogueNumber", "scene", "hasLog", "hasCue", "logScore",
             "logChecksum", "logCount", "ripLogIds", "fileCount", "size",
             "seeders", "leechers", "snatched", "freeTorrent", "reported",
             "time", "description", "fileList", "filePath", "userId",
             "username"
            ],
            array_keys($torrentList[0]),
            'tgroup-json-torrent'
        );

        $nope = new Json\TGroup(
            $this->tgroup,
            $this->userList['nope'],
            new Manager\Torrent(),
        );
        // no infohash if not uploader
        $this->assertEquals(
            ["id", "media", "format", "encoding", "remastered",
             "remasterYear", "remasterTitle", "remasterRecordLabel",
             "remasterCatalogueNumber", "scene", "hasLog", "hasCue", "logScore",
             "logChecksum", "logCount", "ripLogIds", "fileCount", "size",
             "seeders", "leechers", "snatched", "freeTorrent", "reported",
             "time", "description", "fileList", "filePath", "userId",
             "username"
            ],
            array_keys($nope->payload()['torrents'][0]),
            'tgroup-json-nope-torrent'
        );
    }

    public function testTGroupSubscription(): void {
        $sub = new User\Subscription($this->userList['user']);
        $this->assertTrue($sub->subscribeComments('torrents', $this->tgroup->id()));

        $text       = 'phpunit tgroup subscribe ' . randomString();
        $commentMan = new Manager\Comment();
        $comment    = $commentMan->create($this->userList['admin'], 'torrents', 0, $text);
        // TODO: should this be 1?
        $this->assertEquals(0, $comment->pageNum(), 'tgroup-comment-page-num');

        $this->assertEquals([['torrents', $this->tgroup->id()]], $sub->commentSubscriptions(), 'tgroup-tgroup-comment-sub');
        $this->assertEquals(1, $sub->commentTotal(), 'tgroup-tgroup-comment-all');
    }

    public function testTGroupTag(): void {
        $user = $this->userList['admin'];
        $name = 'phpunit.' . randomString(6);

        $tagMan = new Manager\Tag();
        $tag = $tagMan->create($name, $user);
        $this->assertGreaterThan(1, $tag->id(), 'tgroup-tag-create');
        $this->assertEquals(1, $tag->addTGroup($this->tgroup, $user, 10), 'tgroup-tag-add-one');

        $tag2 = $tagMan->create('phpunit.' . randomString(6), $user);
        $this->assertEquals(1, $tag2->addTGroup($this->tgroup, $user, 5), 'tgroup-tag-add-two');
        $this->tgroup->flush();
        $this->assertCount(2, $this->tgroup->tagNameList(), 'tgroup-tag-name-list');
        $this->assertContains($name, $this->tgroup->tagNameList(), 'tgroup-tag-name-find-one');
        $this->assertContains($tag2->name(), $this->tgroup->tagNameList(), 'tgroup-tag-name-find-not');
        $this->assertEquals("#{$name} #{$tag2->name()}", $this->tgroup->hashTag(), 'tgroup-tag-name-list');

        $this->assertEquals(1, $tag->voteTGroup($this->tgroup, $user, 'up'), 'tgroup-tag-upvote');
        $this->assertEquals(1, $tag2->voteTGroup($this->tgroup, $user, 'down'), 'tgroup-tag-downvote');
        $this->assertEquals(ucfirst($name), $this->tgroup->primaryTag(), 'tgroup-tag-primary');

        $this->assertTrue($tag2->removeTGroup($this->tgroup), 'tgroup-tag-remove-exists');
        $tag3 = $tagMan->create('phpunit.' . randomString(6), $user);
        $this->assertFalse($tag3->removeTGroup($this->tgroup), 'tgroup-tag-remove-not-exists');
    }

    public function testLatestUploads(): void {
        // we can at least test the SQL
        $this->assertIsArray((new Manager\Torrent())->latestUploads(5), 'tgroup-latest-uploads');
    }

    public function testTGroupMerge(): void {
        $admin = $this->userList['admin'];
        $user = $this->userList['user'];
        $this->tgroupExtra = $this->manager->create(
            categoryId:      1,
            name:            $this->name . ' merge ' . randomString(10),
            year:            $this->year,
            recordLabel:     $this->recordLabel,
            catalogueNumber: $this->catalogueNumber,
            description:     "Description of {$this->name} merge",
            image:           '',
            releaseType:     (new ReleaseType())->findIdByName('Live album'),
            showcase:        false,
        );
        \GazelleUnitTest\Helper::makeTorrentMusic(
            tgroup:          $this->tgroupExtra,
            user:            $user,
            catalogueNumber: 'UA-MG-1',
        );
        $oldId   = $this->tgroupExtra->id();
        $oldName = $this->tgroupExtra->name();

        (new User\Bookmark($admin))->create('torrent', $oldId);
        (new Manager\Comment())->create($user, 'torrents', $oldId, 'phpunit comment ' . randomString(10));
        $adminVote = new User\Vote($admin);
        $userVote = new User\Vote($user);
        $adminVote->upvote($this->tgroupExtra);
        $userVote->downvote($this->tgroupExtra);

        $this->assertTrue(
            $this->manager->merge(
                $this->tgroupExtra,
                $this->tgroup,
                $this->userList['admin'],
                new Manager\User(),
                new Manager\Vote(),
            ),
            'tgroup-music-merge'
        );

        $siteLog = new Manager\SiteLog(new Manager\User());
        $siteLog->relay();
        $list = $siteLog->tgroupLogList($this->tgroup->id());
        $event = end($list);
        $this->assertStringContainsString("($oldName)", $event['info'], 'tgroup-merge-old-name');
        $this->assertStringContainsString("({$this->tgroup->name()})", $event['info'], 'tgroup-merge-new-name');

        $general = current($siteLog->page(1, 0, ''));
        $this->assertEquals(
            "Group <a href=\"torrents.php?id=$oldId\">$oldId</a> deleted following merge to {$this->tgroup->id()}.",
            $general['message'],
            'tgroup-merge-general'
        );

        $this->assertTrue(
            (new User\Bookmark($admin))->isTorrentBookmarked($this->tgroup->id()),
            'tgroup-merge-bookmark'
        );

        $comment = new Comment\Torrent($this->tgroup->id(), 1, 0);

        // create new vote objects to pick up the state change
        unset($adminVote);
        unset($userVote);
        $adminVote = new User\Vote($admin);
        $userVote = new User\Vote($user);

        $this->assertEquals(1, $adminVote->flush()->vote($this->tgroup), 'tgroup-merge-upvote');
        $this->assertEquals(-1, $userVote->flush()->vote($this->tgroup), 'tgroup-merge-downvote');
    }

    public function testTGroupSplit(): void {
        $list = $this->tgroup->torrentIdList();
        $this->assertCount(3, $list, 'tgroup-has-torrents');
        $torrent = (new Manager\Torrent())->findById($list[1]);
        $this->assertInstanceOf(Torrent::class, $torrent, 'tgroup-id-is-a-torrent');
        $suffix = randomString(10);
        $this->tgroupExtra = (new Manager\TGroup())->createFromTorrent(
            $torrent,
            "phpunit split artist $suffix",
            "php split title $suffix",
            (int)date('Y'),
            new Manager\Artist(),
            new Manager\Bookmark(),
            new Manager\Comment(),
            new Manager\Vote(),
            $this->userList['admin'],
        );
        $this->assertInstanceOf(TGroup::class, $this->tgroupExtra, 'tgroup-is-split');
        $this->assertCount(2, $this->tgroup->flush()->torrentIdList(), 'tgroup-has-one-less-torrent');
        $this->assertCount(1, $this->tgroupExtra->torrentIdList(), 'tgroup-split-has-one-torrent');
    }

    public function testStatsRefresh(): void {
        $this->assertIsInt((new Stats\TGroups())->refresh(), 'tgroup-stats-refresh');
    }

    public function testRemasterList(): void {
        $list = $this->tgroup->remasterList();
        $id_list = $this->tgroup->torrentIdList();
        $this->assertCount(2, $list, 'tgroup-remaster-list');
        $this->assertEquals([$id_list[0]], $list[0]['id_list'], 'tgroup-remaster-list-0-id-list');
        $this->assertEquals([$id_list[1], $id_list[2]], $list[1]['id_list'], 'tgroup-remaster-list-1-id-list');
        $this->assertEquals('Limited Edition', $list[0]['title'], 'tgroup-remaster-title');
        $this->assertEquals('UA-TG-2', $list[0]['catalogue_number'], 'tgroup-remaster-cat-no');
        $this->assertEquals('Unitest Artists', $list[0]['record_label'], 'tgroup-remaster-rec-label');
    }

    public function testTGroupStats(): void {
        (new Stats\TGroups())->refresh();

        $stats = $this->tgroup->stats();
        $this->assertIsInt($stats->downloadTotal(), 'tgroup-stats-download');
        $this->assertIsInt($stats->leechTotal(), 'tgroup-stats-leech');
        $this->assertIsInt($stats->seedingTotal(), 'tgroup-stats-seeding');
        $this->assertIsInt($stats->snatchTotal(), 'tgroup-stats-snatch');

        // test increment
        $total = $stats->bookmarkTotal();
        $this->assertIsInt($stats->bookmarkTotal(), 'tgroup-stats-bookmark');
        $bookmark = new User\Bookmark($this->userList['user']);
        $bookmark->create('torrent', $this->tgroup->id());

        (new Stats\TGroups())->refresh();
        $stats->flush();
        $this->assertEquals($total + 1, $stats->bookmarkTotal(), 'tgroup-stats-update-bookmark');
    }
}
