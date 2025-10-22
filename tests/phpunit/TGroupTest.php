<?php

namespace Gazelle;

use PHPUnit\Framework\TestCase;
use GazelleUnitTest\Helper;

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
            'admin' => Helper::makeUser('tgroup.a.' . randomString(6), 'tgroup'),
            'user'  => Helper::makeUser('tgroup.u.' . randomString(6), 'tgroup'),
            'nope'  => Helper::makeUser('tgroup.n.' . randomString(6), 'tgroup'),
        ];
        $this->userList['admin']->requestContext()->setViewer($this->userList['admin']);
        $this->userList['admin']->setField('PermissionID', MOD)->modify();

        $this->name            = 'phpunit live in ' . randomString(6);
        $this->year            = (int)date('Y');
        $this->recordLabel     = randomString(6) . ' Records';
        $this->catalogueNumber = randomString(3) . '-' . random_int(1000, 2000);
        $this->manager         = new Manager\TGroup();
        $this->tgroup      = $this->manager->create(
            categoryId:      CATEGORY_MUSIC,
            name:            $this->name,
            year:            $this->year,
            recordLabel:     $this->recordLabel,
            catalogueNumber: $this->catalogueNumber,
            description:     "Description of {$this->name}",
            image:           'https://example.com/' . randomString(10) . '.jpg',
            releaseType:     new ReleaseType()->findIdByName('Live album'),
            showcase:        false,
        );

        // and add some torrents to the group
        Helper::makeTorrentMusic(
            tgroup:          $this->tgroup,
            user:            $this->userList['user'],
            catalogueNumber: 'UA-TG-1',
        );
        Helper::makeTorrentMusic(
            tgroup:          $this->tgroup,
            user:            $this->userList['admin'],
            catalogueNumber: 'UA-TG-1',
            format:          'MP3',
            encoding:        'V0',
        );
        Helper::makeTorrentMusic(
            tgroup:          $this->tgroup,
            user:            $this->userList['user'],
            catalogueNumber: 'UA-TG-2',
            title:           'Limited Edition',
        );
    }

    public function tearDown(): void {
        if (isset($this->tgroupExtra)) {
            Helper::removeTGroup($this->tgroupExtra, $this->userList['admin']);
        }
        Helper::removeTGroup($this->tgroup, $this->userList['admin']);
        foreach ($this->userList as $user) {
            $user->remove();
        }
    }

    /* Random artist names may not be in alphabetical order, but
     * will be rendered in order, so arrange as appropriate.
     */
    protected function namePair(string $one, string $two): array {
        return $two < $one ? [$two, $one] : [$one, $two];
    }

    public function testTGroupCreate(): void {
        $this->assertGreaterThan(1, $this->tgroup->id, 'tgroup-create-id');

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
        $find = $this->manager->findById($this->tgroup->id);
        $this->assertInstanceOf(TGroup::class, $find, 'tgroup-instance-of');
        $this->assertEquals($this->tgroup->id, $find->id, 'tgroup-create-find');

        $torMan = new Manager\Torrent();
        $torrent = $torMan->findById($this->tgroup->torrentIdList()[0]);
        $this->assertInstanceOf(Torrent::class, $torrent, 'tgroup-found-torrent');
        $this->assertEquals(1, $torrent->tokenCount(), 'tgroup-torrent-fl-cost');
        $this->assertFalse($this->userList['user']->canSpendFLToken($torrent), 'tgroup-user-no-fltoken');

        $bonus = new User\Bonus($this->userList['user']);
        $this->assertEquals(1, $bonus->addPoints(10000), 'tgroup-user-add-bp');
        $token = new Manager\Bonus()->findBonusItemByLabel('token-1');
        $this->assertEquals(
            Enum\BonusItemPurchaseStatus::success,
            $token->purchase($this->userList['user'], $token->price()),
            'tgroup-user-buy-fltoken'
        );
        $this->assertTrue($this->userList['user']->canSpendFLToken($torrent), 'tgroup-user-fltoken');

        new Stats\Users()->refresh();
        $userStats = $this->userList['user']->stats();
        $this->assertEquals(2, $userStats->uploadTotal(), 'tgroup-user-stats-upload');
        $this->assertEquals(1, $userStats->uniqueGroupTotal(), 'tgroup-user-stats-unique');
        $this->assertEquals(0, $userStats->perfecterFlacTotal(), 'tgroup-user-stats-perfecter');
        $this->assertEquals(1, $this->userList['admin']->stats()->uploadTotal(), 'tgroup-user-admin-upload');
        $this->assertEquals(0.0, $userStats->bpHourlyAccrual(), 'tgroup-user-stats-bp-accrual');

        $timeline = $userStats->timeline();
        $this->assertCount(3, $timeline, 'tgroup-user-timeline-count');
        $this->assertEquals(
            [
                "name",
                "interval",
                "count",
                "data_up",
                "data_down",
                "buffer",
                "bp",
                "uploads",
                "perfect",
                "start",
            ],
            array_keys($timeline[0]),
            'tgroupe-user-timeline-section'
        );
    }

    public function testTGroupBookmark(): void {
        $bookmarker = new User\Bookmark($this->userList['user']);
        $bookmarker->create($this->tgroup);
        $page = $bookmarker->tgroupList(2, 0);
        $this->assertCount(1, $page, 'bookmark-tgroup-page-total');
        $this->assertEquals(
            $this->tgroup->id,
            $page[0]['tgroup_id'],
            'bookmark-tgroup-page-item'
        );
        // no tags, but verify the SQL works
        $this->assertCount(
            0,
            $bookmarker->tgroupTagLeaderboard(),
            'bookmark-tag-leaderboard',
        );
        $payload = new Json\Bookmark\TGroup($bookmarker)->payload();
        $this->assertEquals(
            $this->tgroup->id,
            $payload['bookmarks'][0]['id'],
            'tgroup-json-bookmark-id',
        );
        $this->assertEqualsCanonicalizing(
            $this->tgroup->torrentIdList(),
            array_map(fn ($b) => $b['id'], $payload['bookmarks'][0]['torrents']),
            'tgroup-json-bookmark-torrent-list',
        );

        $this->assertEquals(
            1,
            $bookmarker->removeObject($this->tgroup),
            'tgroup-bookmark-user-remove',
        );

        $bookmarker->create($this->tgroup);
        $this->assertEquals(
            1,
            $bookmarker->remove(),
            'tgroup-bookmark-user-all-remove',
        );
    }

    public function testTGroupArtist(): void {
        $artMan = new Manager\Artist();
        $user   = $this->userList['admin'];
        $artistName = 'phpunit ' . randomString(6) . ' band';
        $this->assertEquals(
            1,
            $this->tgroup->addArtists([ARTIST_MAIN], [$artistName]),
            'tgroup-artist-add'
        );
        $this->assertEquals(
            "$artistName – {$this->tgroup->name()} [{$this->tgroup->year()} Live album]",
            $this->tgroup->text(),
            'tgroup-artist-text'
        );

        $this->assertNotNull($this->tgroup->primaryArtist(), 'tgroup-artist-primary');

        $artistRole = $this->tgroup->artistRole();
        $idList = $artistRole->idList();
        $this->assertCount(1, $idList, 'tgroup-artistrole-idlist');

        $main = $idList[ARTIST_MAIN];
        $this->assertCount(1, $main, 'tgroup-artistrole-main');
        $artist = $artMan->findByName($artistName);
        $this->assertEquals(
            [
                "artists" => [[
                    "id"      => $artist->id,
                    "aliasid" => $artist->aliasId(),
                    "name"    => $artist->name(),
                    "artist"  => $artist->flush(),
                ]],
                "with"      => [],
                "remixedBy" => [],
                "composers" => [],
                "conductor" => [],
                "dj"        => [],
                "producer"  => [],
                "arranger"  => [],
            ],
            $artistRole->roleListByType(),
            'tgroup-artistrole-by-type',
        );
        $this->assertEquals(
            [
                "1" => [[
                    "id"      => $artist->id,
                    "aliasid" => $artist->aliasId(),
                    "name"    => $artist->name(),
                    "artist"  => $artist->flush(),
                ]],
                "2" => null,
                "3" => null,
                "4" => null,
                "5" => null,
                "6" => null,
                "7" => null,
                "8" => null,
            ],
            $artistRole->legacyList(),
            'tgroup-artistrole-legacy',
        );
        $this->assertEquals(
            ["1" => [$artist->name()]],
            $artistRole->nameList(),
            'tgroup-artistrole-namelist',
        );
        $first = current($main);
        $this->assertEquals($artistName, $first['name'], 'tgroup-artist-first-name');

        $foundByArtist = $this->manager->findByArtistReleaseYear(
            $this->tgroup->artistRole()->text(),
            $this->tgroup->name(),
            (int)$this->tgroup->releaseType(),
            (int)$this->tgroup->year(),
        );
        $this->assertEquals(
            $this->tgroup->id,
            $foundByArtist?->id,
            'tgroup-find-name'
        );

        $addName = "$artistName-2";
        $this->assertEquals(
            2,
            $this->tgroup->addArtists(
                [ARTIST_MAIN,     ARTIST_GUEST],
                [$addName, "$artistName-guest"],
            ),
            'tgroup-artist-add-2'
        );
        $this->assertInstanceOf(
            ArtistRole\TGroup::class,
            $this->tgroup->artistRole(),
            'tgroup-has-artistrole'
        );
        $this->assertEquals(
            [
                ARTIST_MAIN  => [$artistName, $addName],
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
        $this->assertEquals(
            'Various DJs',
            $this->tgroup->flush()->artistRole()->text(),
            'tgroup-2manydjs'
        );
        $this->assertEquals(
            1,
            $this->tgroup->artistRole()->removeList([[ARTIST_DJ, $roleAliasList[0][1]]], $user),
            'tgroup-hang-the-dj'
        );
        $this->assertEquals(
            "$addName and $artistName-guest",
            $this->tgroup->flush()->artistRole()->text(),
            'tgroup-dj-final'
        );
        $this->assertEquals(
            [],
            $this->tgroup->artistRole()->matchName([]),
            'match-name-none',
        );
        $this->assertEquals(
            [$addName],
            $this->tgroup->artistRole()->matchName([strtoupper($addName)]),
            'match-name-upper',
        );
    }

    public function testTGroupCoverArt(): void {
        $coverId = $this->tgroup->addCoverArt(
            'https://www.example.com/cover.jpg',
            'cover art summary',
        );
        $this->assertGreaterThan(0, $coverId, 'tgroup-cover-art-add');
        $this->assertEquals(
            1,
            $this->tgroup->removeCoverArt($coverId),
            'tgroup-cover-art-del-ok'
        );
        $this->assertEquals(
            0,
            $this->tgroup->removeCoverArt(9999999),
            'tgroup-cover-art-del-nok'
        );
    }

    public function testTGroupRevision(): void {
        $revisionId = $this->tgroup->createRevision(
            $this->tgroup->description() . "\nmore text",
            'https://www.example.com/image.jpg',
            'phpunit testTGroup summary',
        );
        $this->assertGreaterThan(0, $revisionId, 'tgroup-revision-add');
    }

    public function testTGroupJson(): void {
        $json = new Json\TGroup(
            $this->tgroup,
            $this->userList['user'],
        );
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
            "seeders", "leechers", "snatched", "freeTorrent", "freeReason",
            "reported", "trumpable", "trumpable_reasons", "time", "description",
            "fileList", "filePath", "userId", "username",
            ],
            array_keys($torrentList[0]),
            'tgroup-json-torrent'
        );

        $nope = new Json\TGroup(
            $this->tgroup,
            $this->userList['nope'],
        );
        // no infohash if not uploader
        $this->assertEquals(
            ["id", "media", "format", "encoding", "remastered", "remasterYear",
            "remasterTitle", "remasterRecordLabel", "remasterCatalogueNumber",
            "scene", "hasLog", "hasCue", "logScore", "logChecksum", "logCount",
            "ripLogIds", "fileCount", "size", "seeders", "leechers", "snatched",
            "freeTorrent", "freeReason", "reported", "trumpable",
            "trumpable_reasons", "time", "description", "fileList", "filePath",
            "userId", "username"
            ],
            array_keys($nope->payload()['torrents'][0]),
            'tgroup-json-nope-torrent'
        );
    }

    public function testTGroupSubscription(): void {
        $sub = new User\Subscription($this->userList['user']);
        $this->assertTrue($sub->subscribeComments('torrents', $this->tgroup->id));

        $text       = 'phpunit tgroup subscribe ' . randomString();
        $commentMan = new Manager\Comment();
        $comment    = $commentMan->create($this->userList['admin'], 'torrents', 0, $text);
        // TODO: should this be 1?
        $this->assertEquals(0, $comment->pageNum(), 'tgroup-comment-page-num');

        $this->assertEquals([['torrents', $this->tgroup->id]], $sub->commentSubscriptions(), 'tgroup-tgroup-comment-sub');
        $this->assertEquals(1, $sub->commentTotal(), 'tgroup-tgroup-comment-all');
    }

    public function testTGroupTag(): void {
        $user = $this->userList['admin'];
        $name = 'phpunit.' . randomString(6);

        $tagMan = new Manager\Tag();
        $tag = $tagMan->create($name, $user);
        $this->assertGreaterThan(1, $tag->id, 'tgroup-tag-create');
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
        $this->assertGreaterThanOrEqual(
            0,
            count(new Manager\Torrent()->latestUploads(5)),
            'tgroup-latest-uploads'
        );
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
            releaseType:     new ReleaseType()->findIdByName('Live album'),
            showcase:        false,
        );
        Helper::makeTorrentMusic(
            tgroup:          $this->tgroupExtra,
            user:            $user,
            catalogueNumber: 'UA-MG-1',
        );
        $oldId   = $this->tgroupExtra->id;
        $oldName = $this->tgroupExtra->name();

        new User\Bookmark($admin)->create($this->tgroupExtra);
        new Manager\Comment()->create($user, 'torrents', $oldId, 'phpunit comment ' . randomString(10));
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

        $siteLog = new Manager\SiteLog();
        $list = $siteLog->tgroupLogList($this->tgroup->id);
        $event = end($list);
        $this->assertStringContainsString("($oldName)", $event['info'], 'tgroup-merge-old-name');
        $this->assertStringContainsString("({$this->tgroup->name()})", $event['info'], 'tgroup-merge-new-name');

        $general = current($siteLog->page(1, 0, ''));
        $this->assertEquals(
            "Group <a href=\"torrents.php?id=$oldId\">$oldId</a> deleted following merge to {$this->tgroup->id}.",
            $general['message'],
            'tgroup-merge-general'
        );

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
        $torrent = new Manager\Torrent()->findById($list[1]);
        $this->assertInstanceOf(Torrent::class, $torrent, 'tgroup-id-is-a-torrent');
        $suffix = randomString(10);
        $this->tgroupExtra = new Manager\TGroup()->createFromTorrent(
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

    public function testReleaseType(): void {
        global $Cache;
        $Cache->delete_value(ReleaseType::CACHE_KEY);
        $releaseType = new ReleaseType();
        $this->assertCount(17, $releaseType->list(), 'rel-type-list');
        $this->assertCount(
            22,
            $releaseType->extendedList(),
            'rel-type-extended-list',
        );
        $this->assertEquals(
            6,
            $releaseType->findIdByName('Anthology'),
            'rel-type-find-id',
        );
        $this->assertEquals(
            'Anthology',
            $releaseType->findNameById(6),
            'rel-type-find-name',
        );
        $this->assertEquals(
            'DJ Mixes',
            $releaseType->sectionTitle($releaseType->findIdByName('DJ Mix')),
            'rel-type-section-title',
        );
    }

    public function testStatsRefresh(): void {
        $this->assertGreaterThanOrEqual(
            0,
            new Stats\TGroups()->refresh(),
            'tgroup-stats-refresh'
        );
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
        new Stats\TGroups()->refresh();

        $stats = $this->tgroup->stats();
        $this->assertGreaterThanOrEqual(0, $stats->downloadTotal(), 'tgroup-stats-download');
        $this->assertGreaterThanOrEqual(0, $stats->leechTotal(), 'tgroup-stats-leech');
        $this->assertGreaterThanOrEqual(0, $stats->seedingTotal(), 'tgroup-stats-seeding');
        $this->assertGreaterThanOrEqual(0, $stats->snatchTotal(), 'tgroup-stats-snatch');

        // test increment
        $total = $stats->bookmarkTotal();
        $bookmarker = new User\Bookmark($this->userList['user']);
        $bookmarker->create($this->tgroup);

        new Stats\TGroups()->refresh();
        $stats->flush();
        $this->assertEquals($total + 1, $stats->bookmarkTotal(), 'tgroup-stats-update-bookmark');

        $this->assertTrue(
            $bookmarker->isBookmarked($this->tgroup),
            'tgroup-merge-bookmark',
        );
        $this->assertEquals(1, $bookmarker->tgroupTotal(), 'tgroup-bookmark-tgroupTotal');
        $list = $bookmarker->tgroupBookmarkList();
        $this->assertCount(1, $list, 'tgroup-bookmark-user-total');
        $this->assertEquals(
            $this->tgroup->id,
            $list[0]['tgroup_id'],
            'tgroup-bookmark-user-id',
        );
        $this->assertEquals(
            3,
            $bookmarker->torrentTotal(),
            'tgroup-bookmark-tgroup-total',
        );
        // artists have not been assigned, but this tests the SQL
        $this->assertEquals(
            [],
            $bookmarker->tgroupArtistLeaderboard(),
            'tgroup-bookmark-leaderboard',
        );
        $this->assertEquals(
            0,
            $bookmarker->tgroupArtistTotal(),
            'tgroup-bookmark-artist-total',
        );

        Helper::generateTorrentSnatch(
            new Torrent($this->tgroup->torrentIdList()[0]),
            $bookmarker->user()
        );
        $this->assertEquals(
            1,
            $bookmarker->removeSnatched(),
            'tgroup-bookmark-remove-snatched',
        );
        $this->assertFalse(
            $bookmarker->isBookmarked($this->tgroup),
            'tgroup-bookmark-unsnatched',
        );
    }

    public function testTGroupNameDJRender(): void {
        $artistName = [
            'phpunit-name-' . randomString(10),
            'phpunit-name-' . randomString(10),
            'phpunit-name-' . randomString(10),
            'phpunit-name-' . randomString(10),
        ];
        $artMan = new Manager\Artist();

        $this->tgroup->addArtists([ARTIST_DJ], [$artistName[0]]);
        $this->assertEquals(
            $artistName[0],
            $this->tgroup->artistRole()->text(),
            'tgroup-dj-1-text',
        );
        $artist = $artMan->findByName($artistName[0]);
        $this->assertEquals(
            "<a href=\"artist.php?id={$artist->id}\" dir=\"ltr\">{$artist->name()}</a>",
            $this->tgroup->artistRole()->link(),
            'tgroup-dj-1-html',
        );
        $this->tgroup->addArtists([ARTIST_DJ], [$artistName[1]]);
        $this->assertEquals(
            implode(' and ', $this->namePair($artistName[0], $artistName[1])),
            $this->tgroup->artistRole()->text(),
            'tgroup-dj-2-text',
        );
        // Getting two artist objects in alphabetical order is another matter entirely
        $artMan = new Manager\Artist();
        $artistMap = [
            $artistName[0] => $artMan->findByName($artistName[0]),
            $artistName[1] => $artMan->findByName($artistName[1]),
        ];
        $order = $this->namePair($artistName[0], $artistName[1]);
        $this->assertEquals(
            implode(
                ' &amp; ',
                array_map(
                    fn ($a) => "<a href=\"artist.php?id={$a->id}\" dir=\"ltr\">{$a->name()}</a>",
                    [
                        $artistMap[$order[0]],
                        $artistMap[$order[1]],
                    ],
                )
            ),
            $this->tgroup->artistRole()->link(),
            'tgroup-dj-2-link',
        );
        $this->tgroup->addArtists([ARTIST_DJ], [$artistName[2]]);
        $this->assertEquals(
            "Various DJs",
            $this->tgroup->artistRole()->text(),
            'tgroup-dj-3-text',
        );
        $sortedArtistList = [
            $artistName[0],
            $artistName[1],
            $artistName[2],
        ];
        sort($sortedArtistList);
        $this->assertEquals(
            '<span class="tooltip" style="float: none"  title="'
                . implode(' ⁝ ', $sortedArtistList)
                . '">Various DJs</span>',
            $this->tgroup->artistRole()->link(),
            'tgroup-dj-3-link',
        );
        $this->tgroup->addArtists([ARTIST_MAIN], [$artistName[3]]);
        $this->assertEquals(
            "Various DJs",
            $this->tgroup->artistRole()->text(),
            'tgroup-dj-4-text',
        );
    }

    public function testTGroupNameClassicalEraRender(): void {
        $artistName = [
            'phpunit-name-' . randomString(10),
            'phpunit-name-' . randomString(10),
            'phpunit-name-' . randomString(10),
            'phpunit-name-' . randomString(10),
            'phpunit-name-' . randomString(10),
            'phpunit-name-' . randomString(10),
            'phpunit-name-' . randomString(10),
            'phpunit-name-' . randomString(10),
            'phpunit-name-' . randomString(10),
            'phpunit-name-' . randomString(10),
            'phpunit-name-' . randomString(10),
            'phpunit-name-' . randomString(10),
        ];

        $this->tgroup->addArtists([ARTIST_COMPOSER], [$artistName[0]]);
        $this->assertEquals(
            $artistName[0],
            $this->tgroup->artistRole()->text(),
            'tgroup-composer-1-text',
        );
        $this->tgroup->addArtists([ARTIST_CONDUCTOR], [$artistName[1]]);
        $this->assertEquals(
            "{$artistName[0]} conducted by {$artistName[1]}",
            $this->tgroup->artistRole()->text(),
            'tgroup-composer-1-conductor-1-text',
        );
        $this->tgroup->addArtists([ARTIST_CONDUCTOR], [$artistName[2]]);
        $this->assertEquals(
            "{$artistName[0]} conducted by "
                . implode(' and ', $this->namePair($artistName[1], $artistName[2])),
            $this->tgroup->artistRole()->text(),
            'tgroup-composer-1-conductor-2-text',
        );
        $this->tgroup->addArtists([ARTIST_COMPOSER], [$artistName[3]]);
        $this->assertEquals(
            implode(' and ', $this->namePair($artistName[0], $artistName[3]))
                . ' conducted by '
                . implode(' and ', $this->namePair($artistName[1], $artistName[2])),
            $this->tgroup->artistRole()->text(),
            'tgroup-composer-2-conductor-2-text',
        );
        $this->tgroup->addArtists([ARTIST_ARRANGER], [$artistName[4]]);
        $this->assertEquals(
            implode(' and ', $this->namePair($artistName[0], $artistName[3]))
                . " arranged by {$artistName[4]} conducted by "
                . implode(' and ', $this->namePair($artistName[1], $artistName[2])),
            $this->tgroup->artistRole()->text(),
            'tgroup-composer-2-arranger-1-conductor-2-text',
        );
        $this->tgroup->addArtists([ARTIST_MAIN], [$artistName[5]]);
        $this->assertEquals(
            implode(' and ', $this->namePair($artistName[0], $artistName[3]))
                . " arranged by {$artistName[4]} performed by {$artistName[5]} under "
                . implode(' and ', $this->namePair($artistName[1], $artistName[2])),
            $this->tgroup->artistRole()->text(),
            'tgroup-main-1-composer-2-arranger-1-conductor-2-text',
        );
        $this->tgroup->addArtists([ARTIST_MAIN], [$artistName[6]]);
        $this->assertEquals(
            implode(' and ', $this->namePair($artistName[0], $artistName[3]))
                . " arranged by {$artistName[4]} performed by "
                . implode(' and ', $this->namePair($artistName[5], $artistName[6]))
                . ' under '
                . implode(' and ', $this->namePair($artistName[1], $artistName[2])),
            $this->tgroup->artistRole()->text(),
            'tgroup-main-2-composer-2-arranger-1-conductor-2-text',
        );
        $this->tgroup->addArtists([ARTIST_ARRANGER], [$artistName[7]]);
        $this->assertEquals(
            implode(' and ', $this->namePair($artistName[0], $artistName[3]))
                . ' arranged by '
                . implode(' and ', $this->namePair($artistName[4], $artistName[7]))
                . ' performed by '
                . implode(' and ', $this->namePair($artistName[5], $artistName[6]))
                . ' under '
                . implode(' and ', $this->namePair($artistName[1], $artistName[2])),
            $this->tgroup->artistRole()->text(),
            'tgroup-main-2-composer-2-arranger-2-conductor-2-text',
        );
        $this->tgroup->addArtists(
            [ARTIST_COMPOSER, ARTIST_CONDUCTOR, ARTIST_MAIN, ARTIST_ARRANGER],
            [$artistName[8], $artistName[9], $artistName[10], $artistName[11], ]
        );
        $this->assertEquals(
            "Various Composers arranged by Various Arrangers performed by Various Artists under Various Conductors",
            $this->tgroup->artistRole()->text(),
            'tgroup-main-3-composer-3-arranger-3-conductor-3-text',
        );
    }

    public function testTGroupArtistRole(): void {
        $artistName = 'phpunit-name-' . randomString(10);

        $this->tgroup->addArtists(
            [ARTIST_COMPOSER, ARTIST_CONDUCTOR, ARTIST_MAIN, ARTIST_PRODUCER],
            [$artistName,     $artistName,      $artistName, $artistName]
        );
        $artistRole = new Manager\Artist()->findByName($artistName)->artistRole();
        $this->assertCount(8, $artistRole, 'tgroup-artistrole-count');
        $this->assertEquals(0, $artistRole[ARTIST_GUEST], 'tgroup-artistrole-guest');
        $this->assertEquals(1, $artistRole[ARTIST_COMPOSER], 'tgroup-artistrole-composer');
        $this->assertEquals(1, $artistRole[ARTIST_CONDUCTOR], 'tgroup-artistrole-conductor');
        $this->assertEquals(1, $artistRole[ARTIST_MAIN], 'tgroup-artistrole-main');
        $this->assertEquals(1, $artistRole[ARTIST_PRODUCER], 'tgroup-artistrole-producer');
    }
}
