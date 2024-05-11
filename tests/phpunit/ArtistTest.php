<?php

use PHPUnit\Framework\TestCase;
use Gazelle\Enum\CollageType;

require_once(__DIR__ . '/../../lib/bootstrap.php');
require_once(__DIR__ . '/../helper.php');

class ArtistTest extends TestCase {
    protected Gazelle\Artist  $artist;
    protected Gazelle\Collage $collage;
    protected array           $tgroupList;
    protected Gazelle\User    $user;
    protected Gazelle\User    $extra;
    protected array           $artistIdList = [];

    public function setUp(): void {
        $this->user = Helper::makeUser('arty.' . randomString(10), 'artist');
    }

    public function tearDown(): void {
        $logger  = new \Gazelle\Log();
        $manager = new \Gazelle\Manager\Artist();
        foreach ($this->artistIdList as $artistId) {
            $artist = $manager->findById($artistId);
            if ($artist) {
                $artist->toggleAttr('locked', false);
                $artist->remove($this->user, $logger);
            }
        }
        if (isset($this->extra)) {
            $this->extra->remove();
        }
        if (isset($this->collage)) {
            $this->collage->hardRemove();
        }
        if (isset($this->tgroupList)) {
            foreach ($this->tgroupList as $tgroup) {
                Helper::removeTGroup($tgroup, $this->user);
            }
        }
        $this->user->remove();
    }

    public function testArtistCreate(): void {
        $manager = new \Gazelle\Manager\Artist();
        $this->assertNull($manager->findById(-666), 'artist-find-fail');

        $artist = $manager->create('phpunit.' . randomString(12));
        $this->artistIdList[] = $artist->id();

        (Gazelle\DB::DB())->prepared_query("
            INSERT INTO artist_usage
                   (artist_id, role, uses)
            VALUES (?,         ?,    ?)
            ", $artist->id(), '1', RANDOM_ARTIST_MIN_ENTRIES
        );
        // If the following test fails locally:
        // before test run: TRUNCATE TABLE artist_usage;
        // after test run: (new \Gazelle\Stats\Artists)->updateUsage();
        $this->assertEquals($artist->id(), $manager->findRandom()->id(), 'artist-find-random');
        $this->assertNull($manager->findByIdAndRevision($artist->id(), -666), 'artist-find-revision-fail');

        $this->assertGreaterThan(0, $artist->id(), 'artist-create-artist-id');
        $this->assertGreaterThan(0, $artist->aliasId(), 'artist-create-alias-id');
        $artist = $manager->findById($artist->id());
        $this->assertInstanceOf(\Gazelle\Artist::class, $artist, 'artist-is-an-artist');

        $this->assertNull($manager->findByIdAndRevision($artist->id(), -1), 'artist-is-an-unrevised-artist');
        // empty, but at least it tests the SQL
        $this->assertCount(0, $artist->tagLeaderboard(), 'artist-tag-leaderboard');
    }

    public function testArtistInfo(): void {
        $manager = new \Gazelle\Manager\Artist();
        $artist = $manager->create('phpunit.' . randomString(12));
        $this->artistIdList[] = $artist->id();

        $this->assertEquals("<a href=\"artist.php?id={$artist->id()}\">{$artist->name()}</a>", $artist->link(), 'artist-link');
        $this->assertEquals("artist.php?id={$artist->id()}", $artist->location(), 'artist-location');
        $this->assertNull($artist->body(), 'artist-body-null');
        $this->assertNull($artist->image(), 'artist-image-null');
        $this->assertFalse($artist->isLocked(), 'artist-is-unlocked');
        $this->assertTrue($artist->toggleAttr('locked', true), 'artist-toggle-locked');
        $this->assertTrue($artist->isLocked(), 'artist-is-locked');
    }

    public function testArtistRevision(): void {
        $manager = new \Gazelle\Manager\Artist();
        $artist = $manager->create('phpunit.' . randomString(12));
        $this->artistIdList[] = $artist->id();

        $revision = $artist->createRevision(
            body:    'phpunit body test',
            image:   'https://example.com/artist.jpg',
            summary: ['phpunit first revision'],
            user:    $this->user,
        );
        $this->assertGreaterThan(0, $revision, 'artist-revision-1-id');
        $this->assertEquals('phpunit body test', $artist->body(), 'artist-body-revised');

        $rev2 = $artist->createRevision(
            body:    'phpunit body test revised',
            image:   'https://example.com/artist-revised.jpg',
            summary: ['phpunit second revision'],
            user:    $this->user,
        );
        $this->assertEquals($revision + 1, $rev2, 'artist-revision-2');
        $this->assertEquals('https://example.com/artist-revised.jpg', $artist->image(), 'artist-image-revised');

        $artistV1 = $manager->findByIdAndRevision($artist->id(), $revision);
        $this->assertNotNull($artistV1, 'artist-revision-1-found');
        $this->assertEquals('phpunit body test', $artistV1->body(), 'artist-body-rev-1');

        $artistV2 = $manager->findByIdAndRevision($artist->id(), $rev2);
        $this->assertEquals('https://example.com/artist-revised.jpg', $artistV2->image(), 'artist-image-rev-2');

        $list = $artist->revisionList();
        $this->assertEquals('phpunit second revision', $list[0]['summary']);
        $this->assertEquals($revision, $list[1]['revision']);

        $rev3 = $artist->revertRevision($revision, $this->user);
        $this->assertCount(3, $artist->revisionList());
        $this->assertEquals($artistV1->body(), $artist->body(), 'artist-body-rev-3');
    }

    public function testArtistAlias(): void {
        $manager = new \Gazelle\Manager\Artist();
        $logger  = new \Gazelle\Log();
        $artist = $manager->create('phpunit.' . randomString(12));
        $this->artistIdList[] = $artist->id();

        $this->assertEquals($artist->id(), $artist->id(), 'artist-find-by-alias-id');
        $this->assertEquals($artist->id(), $manager->findByName($artist->name())->id(), 'artist-find-by-alias-name');
        $this->assertEquals($artist->aliasId(), $manager->findByName($artist->name())->aliasId(), 'artist-find-aliasid-by-alias-name');
        $this->assertEquals(1, $manager->aliasUseTotal($artist->aliasId()), 'artist-sole-alias');
        $this->assertCount(0, $manager->tgroupList($artist->aliasId(), new Gazelle\Manager\TGroup()), 'artist-no-tgroup');

        $aliasName = $artist->name() . '-alias';
        $newId = $artist->addAlias($aliasName, 0, $this->user, $logger);
        $this->assertEquals($artist->aliasId() + 1, $newId, 'artist-new-alias');
        $this->assertEquals(2, $manager->aliasUseTotal($artist->aliasId()), 'artist-two-alias');

        $artist = $manager->findByName($aliasName);
        $this->assertEquals($artist->id(), $artist->id(), 'artist-fetch-artist-id');
        $this->assertEquals($newId, $artist->aliasId(), 'artist-fetch-alias-id');

        $this->assertEquals(1, $artist->removeAlias($newId, $this->user, $logger), 'artist-remove-alias');
    }

    public function testArtistNonRedirAlias(): void {
        $manager = new \Gazelle\Manager\Artist();
        $logger  = new \Gazelle\Log();
        $artist = $manager->create('phpunit.' . randomString(12));
        $this->artistIdList[] = $artist->id();

        $aliasName = $artist->name() . '-reformed';
        $newId = $artist->addAlias($aliasName, $artist->id(), $this->user, $logger);
        $this->assertEquals($artist->aliasId() + 1, $newId, 'artist-new-non-redirect');
    }

    public function testArtistMerge(): void {
        $manager = new \Gazelle\Manager\Artist();
        $oldName = 'phpunit.artist.' . randomString(12);
        $old = $manager->create($oldName);
        $oldAliasId = $old->aliasId();
        $this->artistIdList[] = $old->id();

        $newName = 'phpunit.artist.' . randomString(12);
        $new = $manager->create($newName);
        $this->artistIdList[] = $new->id();

        $userBk = new Gazelle\User\Bookmark($this->user);
        $userBk->create('artist', $old->id());

        $commentMan = new \Gazelle\Manager\Comment();
        $postList = [
            $commentMan->create($this->user, 'artist', $old->id(), 'phpunit merge ' . randomString(6)),
            $commentMan->create($this->user, 'artist', $new->id(), 'phpunit merge ' . randomString(6)),
        ];

        $this->extra = Helper::makeUser('merge.' . randomString(10), 'merge');
        $extraBk = new Gazelle\User\Bookmark($this->extra);
        $extraBk->create('artist', $old->id());
        $extraBk->create('artist', $new->id());

        $log = new Gazelle\Log();
        $collMan = new Gazelle\Manager\Collage();
        $this->collage = $collMan->create(
            user:        $this->user,
            categoryId:  CollageType::artist->value,
            name:        'phpunit merge artist ' . randomString(10),
            description: 'phpunit merge artist description',
            tagList:     'jazz',
            logger:      $log,
        );
        $this->collage->addEntry($old->id(), $this->user);

        $this->tgroupList = [
            Helper::makeTGroupMusic(
                $this->user,
                'phpunit artist merge1 ' . randomString(10),
                [[ARTIST_MAIN], [$oldName]],
                ['hip.hop'],
            ),
            Helper::makeTGroupMusic(
                $this->user,
                'phpunit artist merge2 ' . randomString(10),
                [[ARTIST_MAIN], [$oldName, $newName]],
                ['hip.hop'],
            ),
        ];

        $this->assertEquals(
            1,
            $new->merge(
                $old,
                false,
                $this->user,
                new Gazelle\Manager\Collage(),
                new Gazelle\Manager\Comment(),
                new Gazelle\Manager\Request(),
                new Gazelle\Manager\TGroup(),
                $log,
            ),
            'artist-merge-n',
        );
        $this->assertNull($manager->findById($old->id()), 'art-merge-no-old');
        $this->assertTrue($userBk->isArtistBookmarked($new->id()), 'art-merge-user-bookmarked-new');
        $this->assertTrue($extraBk->isArtistBookmarked($new->id()), 'art-merge-extra-bookmarked-new');
        $this->assertCount(1, $extraBk->artistList(), 'art-merge-extra-bookmarked-list');

        // FIXME: flushed collage objects cannot be refreshed
        $merged = $collMan->findById($this->collage->id());
        $this->assertEquals([$new->id()], $merged->entryList(), 'art-merge-collage');

        $comment = new \Gazelle\Comment\Artist($new->id(), 1, 0);
        $comment->load(); // FIXME: load() should not be necessary
        $this->assertEquals(
            [$postList[0]->id(), $postList[1]->id()],
            array_map(fn($p) => $p['ID'], $comment->thread()),
            'art-merge-comments'
        );

        $n = 0;
        foreach ($this->tgroupList as $tgroup) {
            ++$n;
            $artistRole = $tgroup->flush()->artistRole();
            $this->assertEquals(
                [ARTIST_MAIN => [['id' => $new->id(), 'name' => $oldName, 'aliasid' => $oldAliasId]]],
                $artistRole->idList(),
                "art-merge-ar-$n"
            );
        }
    }

    public function testArtistModify(): void {
        $manager = new \Gazelle\Manager\Artist();
        $artist = $manager->create('phpunit.' . randomString(12));
        $this->artistIdList[] = $artist->id();

        $this->assertTrue(
            $artist->setField('body', 'body modification')->setUpdateUser($this->user)->modify(),
            'artist-modify-body'
        );
        $this->assertCount(1, $artist->revisionList());
        $this->assertTrue(
            $artist->setField('VanityHouse', true)->setUpdateUser($this->user)->modify(),
            'artist-modify-showcase'
        );
        $this->assertCount(1, $artist->revisionList());

        $this->assertTrue(
            $artist ->setField('image', 'https://example.com/update.png')
                ->setField('summary', 'You look nice in a suit')
                ->setUpdateUser($this->user)
                ->modify(),
            'artist-modify-image'
        );
        $this->assertCount(2, $artist->revisionList());
    }

    public function testArtistRename(): void {
        $manager = new \Gazelle\Manager\Artist();
        $artist = $manager->create('phpunit.' . randomString(12));
        $this->artistIdList[] = $artist->id();

        $oldName = $artist->name();
        $rename = $artist->name() . '-rename';
        $this->assertEquals(
            $artist->aliasId() + 1,
            $artist->renameAlias($artist->aliasId(), $rename, $this->user, new Gazelle\Manager\Request(), new Gazelle\Manager\TGroup()),
            'alias-rename'
        );
        $this->assertContains($rename, $artist->aliasNameList(), 'alias-is-renamed');
        $newAlias = array_filter($artist->aliasList(), fn($v) => $v['name'] == $rename)[0];
        $oldAlias = array_filter($artist->aliasList(), fn($v) => $v['name'] == $oldName)[0];
        $this->assertEquals($newAlias['alias_id'], $oldAlias['redirect_id'], 'alias-is-redirected');
    }

    public function testArtistRenamePrimary(): void {
        $manager = new \Gazelle\Manager\Artist();
        $artist = $manager->create('phpunit.' . randomString(12));
        $this->artistIdList[] = $artist->id();

        $commentMan = new \Gazelle\Manager\Comment();
        $post = $commentMan->create($this->user, 'artist', $artist->id(), 'phpunit smart rename ' . randomString(6));

        $requestMan = new Gazelle\Manager\Request();
        $request = $requestMan->create(
            user:            $this->user,
            bounty:          100 * 1024 ** 2,
            categoryId:      (new Gazelle\Manager\Category())->findIdByName('Music'),
            year:            (int)date('Y'),
            title:           'phpunit smart rename ' . randomString(6),
            image:           '',
            description:     'This is a unit test description',
            recordLabel:     'Unitest Artists',
            catalogueNumber: 'UA-7890',
            releaseType:     1,
            encodingList:    'Lossless',
            formatList:      'MP3',
            mediaList:       'CD',
            logCue:          'Log (100%) + Cue',
            checksum:        true,
            oclc:            '',
        );
        $request->artistRole()->set(
            [ARTIST_MAIN => [$artist->name()]],
            $this->user,
            new Gazelle\Manager\Artist(),
        );

        $this->tgroupList = [
            Helper::makeTGroupMusic(
                $this->user,
                'phpunit artist smart rename ' . randomString(10),
                [[ARTIST_MAIN], [$artist->name()]],
                ['deep.house'],
            ),
        ];

        $name = $artist->name() . '-rename2';
        $artist->renameAlias(
            $artist->primaryAliasId(),
            $name,
            $this->user,
            $requestMan,
            new \Gazelle\Manager\TGroup(),
        );
        $this->assertEquals($name, $artist->name(), 'artist-is-smart-renamed');

        $commentPage = new Gazelle\Comment\Artist($artist->id(), 1, 0);
        $commentPage->load();
        $threadList = $commentPage->threadList(new Gazelle\Manager\User());
        $this->assertCount(1, $threadList, 'artist-renamed-comments');
        $this->assertEquals($post->id(), $threadList[0]['postId'], 'artist-renamed-comments');

        $request->flush();
        $idList = $request->artistRole()->idList();
        $this->assertEquals($artist->id(), $idList[ARTIST_MAIN][0]['id'], 'artist-renamed-request');
        $request->remove();

        $this->tgroupList[0]->flush();
        $idList = $this->tgroupList[0]->artistRole()->idList();
        $this->assertEquals($artist->id(), $idList[ARTIST_MAIN][0]['id'], 'artist-renamed-tgroup');
    }

    public function testRenameAliasCapchange(): void {
        $artistMan = new \Gazelle\Manager\Artist();
        $reqMan = new Gazelle\Manager\Request();
        $tgMan = new \Gazelle\Manager\TGroup();
        $logger  = new \Gazelle\Log();
        $mainName = 'phpunit.' . randomString(12);
        $artist = $artistMan->create($mainName);
        $mainAliasId = $artist->aliasId();
        $aliasName = 'phpunit.' . randomString(12) . '-alias';
        $artist->addAlias($aliasName, null, $this->user, $logger);

        $aliasUpper = strtoupper($aliasName);
        $aliasId = $artist->getAlias($aliasName);
        $this->assertNotNull($aliasId, 'artist-rename-capchange-create');
        $this->assertEquals($aliasId, $artist->renameAlias($aliasId, $aliasUpper, $this->user, $reqMan, $tgMan), 'artist-rename-capchange-1');
        $this->assertEquals($aliasId, $artist->getAlias($aliasName), 'artist-rename-capchange-2');
        $this->assertEquals($mainAliasId, $artist->primaryAliasId(), 'artist-rename-capchange-sanity');
    }

    public function testRenameAliasNraMerge(): void {
        $artistMan = new \Gazelle\Manager\Artist();
        $reqMan = new Gazelle\Manager\Request();
        $tgMan = new \Gazelle\Manager\TGroup();
        $logger  = new \Gazelle\Log();
        $mainName = 'phpunit.' . randomString(12);
        $artist = $artistMan->create($mainName);
        $mainAliasId = $artist->aliasId();

        // create NRA
        $a1Name = 'phpunit.' . randomString(12) . '-alias';
        $a1Id = $artist->addAlias($a1Name, null, $this->user, $logger);

        // add RA
        $raAliasName = $a1Name . '-raalias';
        $raId = $artist->addAlias($raAliasName, $a1Id, $this->user, $logger);

        // create second NRA
        $a2Name = 'phpunit.' . randomString(12) . '-alias';
        $a2Id = $artist->addAlias($a2Name, null, $this->user, $logger);

        $this->assertEquals($a2Id, $artist->renameAlias($a1Id, $a2Name, $this->user, $reqMan, $tgMan), 'artist-rename-nramerge-1');

        $a1 = $artist->aliasList()[$a1Id];
        $a2 = $artist->aliasList()[$a2Id];
        $ra = $artist->aliasList()[$raId];

        $this->assertEquals($a2Id, $a1['redirect_id'], 'artist-rename-nramerge-2');
        $this->assertEquals($a2Id, $ra['redirect_id'], 'artist-rename-nramerge-3');
        $this->assertEquals($a1Name, $a1['name'], 'artist-rename-nramerge-4');
        $this->assertEquals($a2Id, $a2['alias_id'], 'artist-rename-nramerge-5');
        $this->assertEquals($mainAliasId, $artist->primaryAliasId(), 'artist-rename-nramerge-sanity');

        // rename a2 and test redirects
        $a2NewName = 'phpunit.' . randomString(12) . '-new';
        $a3Id = $artist->renameAlias($a2Id, $a2NewName, $this->user, $reqMan, $tgMan);
        $this->assertNotEquals($a2Id, $a3Id, 'artist-rename-nramerge-6');

        $a1 = $artist->aliasList()[$a1Id];
        $a2 = $artist->aliasList()[$a2Id];
        $ra = $artist->aliasList()[$raId];
        $a3 = $artist->aliasList()[$a3Id];
        $this->assertEquals($a3Id, $a3['alias_id'], 'artist-rename-id-simple');
        $this->assertEquals($a2NewName, $a3['name'], 'artist-rename-name-simple');
        $this->assertEquals($a3Id, $a1['redirect_id'], 'artist-rename-redirect-1');
        $this->assertEquals($a3Id, $a2['redirect_id'], 'artist-rename-redirect-2');
        $this->assertEquals($a3Id, $ra['redirect_id'], 'artist-rename-redirect-3');
    }

    public function testArtistSimilar(): void {
        $manager = new \Gazelle\Manager\Artist();
        $artist = $manager->create('phpunit.artsim.' . randomString(12));
        $this->artistIdList[] = $artist->id();
        $this->extra = Helper::makeUser('art2.' . randomString(10), 'artist');

        $other1 = $manager->create('phpunit.other1.' . randomString(12));
        $other2 = $manager->create('phpunit.other2.' . randomString(12));

        $this->artistIdList[] = $other1->id();
        $this->artistIdList[] = $other2->id();

        $this->assertFalse($artist->similar()->voteSimilar($this->extra, $artist, true), 'artist-vote-self');

        $logger  = new \Gazelle\Log();
        $this->assertEquals(1, $artist->similar()->addSimilar($other1, $this->user, $logger), 'artist-add-other1');
        $this->assertEquals(0, $artist->similar()->addSimilar($other1, $this->user, $logger), 'artist-read-other1');
        $this->assertEquals(1, $artist->similar()->addSimilar($other2, $this->user, $logger), 'artist-add-other2');
        $this->assertEquals(1, $other1->similar()->addSimilar($other2, $this->user, $logger), 'artist-other1-add-other2');

        $this->assertTrue($artist->similar()->voteSimilar($this->extra, $other1, true), 'artist-vote-up-other1');
        $this->assertFalse($artist->similar()->voteSimilar($this->extra, $other1, true), 'artist-revote-up-other1');
        $this->assertTrue($other1->similar()->voteSimilar($this->extra, $other2, false), 'artist-vote-down-other2');

        $this->assertEquals(
            [
                [
                    'artist_id'  => $other1->id(),
                    'name'       => $other1->name(),
                    'score'      => 300,
                    'similar_id' => $artist->similar()->findSimilarId($other1),
                ],
                [
                    'artist_id'  => $other2->id(),
                    'name'       => $other2->name(),
                    'score'      => 200,
                    'similar_id' => $artist->similar()->findSimilarId($other2),
                ],
            ],
            $artist->similar()->info(),
            'artist-similar-list'
        );

        $graph = $artist->similar()->similarGraph(100, 100);
        $this->assertCount(2, $graph, 'artist-similar-count');
        $this->assertEquals(
            [$other1->id(), $other2->id()],
            array_values(array_map(fn($sim) => $sim['artist_id'], $graph)),
            'artist-similar-id-list'
        );
        $this->assertEquals($other2->id(), $graph[$other1->id()]['related'][0], 'artist-sim-related');
        $this->assertLessThan($graph[$other1->id()]['proportion'], $graph[$other2->id()]['proportion'], 'artist-sim-proportion');

        $requestMan = new \Gazelle\Manager\Request();
        $this->assertFalse($artist->similar()->removeSimilar($artist, $this->extra, $logger), 'artist-remove-similar-self');
        $this->assertTrue($artist->similar()->removeSimilar($other2, $this->extra, $logger), 'artist-remove-other');
        $this->assertFalse($artist->similar()->removeSimilar($other2, $this->extra, $logger), 'artist-re-remove-other');
    }

    public function testArtistJson(): void {
        $manager = new \Gazelle\Manager\Artist();
        $artist = $manager->create('phpunit.' . randomString(12));
        $this->artistIdList[] = $artist->id();

        $json = (new \Gazelle\Json\Artist(
            $artist,
            $this->user,
            new Gazelle\User\Bookmark($this->user),
            new Gazelle\Manager\Request(),
            new Gazelle\Manager\TGroup(),
            new Gazelle\Manager\Torrent(),
        ));
        $this->assertInstanceOf(\Gazelle\Json\Artist::class, $json->setReleasesOnly(true), 'artist-json-set-releases');
        $payload = $json->payload();
        $this->assertIsArray($payload, 'artist-json-payload');
        $this->assertEquals($artist->id(), $payload['id'], 'artist-payload-id');
        $this->assertEquals($artist->name(), $payload['name'], 'artist-payload-name');
        $this->assertCount(0, $payload['tags'], 'artist-payload-tags');
        $this->assertCount(0, $payload['similarArtists'], 'artist-payload-similar-artists');
        $this->assertCount(0, $payload['torrentgroup'], 'artist-payload-torrentgroup');
        $this->assertCount(0, $payload['requests'], 'artist-payload-requests');
        $this->assertIsArray($payload['statistics'], 'artist-payload-statistics');
    }

    public function testArtistBookmark(): void {
        $name = 'phpunit.' . randomString(12);
        $manager = new \Gazelle\Manager\Artist();
        $artist = $manager->create($name);
        $this->artistIdList[] = $artist->id();

        (new Gazelle\User\Bookmark($this->user))->create('artist', $artist->id());
        $json = new Gazelle\Json\Bookmark\Artist(new Gazelle\User\Bookmark($this->user));
        $this->assertEquals(
            [[
                'artistId'   => $artist->id(),
                'artistName' => $name,
            ]],
            $json->payload(),
            'artist-json-bookmark-payload'
        );
    }

    public function testArtistDiscogs(): void {
        $manager = new \Gazelle\Manager\Artist();
        $artist = $manager->create('phpunit.' . randomString(12));
        $this->artistIdList[] = $artist->id();

        $id = -100000 + random_int(1, 100000);
        $discogs = new Gazelle\Util\Discogs(
            id: $id,
            stem: 'discogs phpunit',
            name: 'discogs phpunit',
            sequence: 2,
        );
        $this->assertEquals($id, $discogs->id(), 'artist-discogs-id');
        $this->assertEquals('discogs phpunit', $discogs->name(), 'artist-discogs-name');
        $this->assertEquals('discogs phpunit', $discogs->stem(), 'artist-discogs-stem');
        $this->assertEquals(2, $discogs->sequence(), 'artist-discogs-sequence');

        $artist->setField('discogs', $discogs)->setUpdateUser($this->user)->modify();
        $this->assertEquals('discogs phpunit', $artist->discogs()->name(), 'artist-self-discogs-name');
    }
}
