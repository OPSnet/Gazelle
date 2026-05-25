<?php

namespace Gazelle;

use PHPUnit\Framework\TestCase;
use GazelleUnitTest\Helper;
use Gazelle\Enum\CollageType;
use Gazelle\Enum\CommentType;

class ArtistTest extends TestCase {
    protected Artist  $artist;
    protected Collage $collage;
    protected array   $tgroupList;
    protected User    $user;
    protected User    $extra;
    protected array   $artistIdList = [];

    protected function renderAutocompleteWidget(array $autocomplete): string {
        return \Gazelle\Util\Twig::factory(new Manager\User())->render(
            'artist/widget.twig',
            $autocomplete['widget']
        );
    }

    public function setUp(): void {
        $this->user = Helper::makeUser('arty.' . randomString(10), 'artist');
        $this->user->requestContext()->setViewer($this->user);
    }

    public function tearDown(): void {
        $manager = new Manager\Artist();
        foreach ($this->artistIdList as $artistId) {
            $manager->findById($artistId)?->remove();
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
        $this->user->requestContext()->unsetViewer();
        $this->user->remove();
    }

    public function testArtistCreate(): void {
        $manager = new Manager\Artist();
        $this->assertNull($manager->findById(-666), 'artist-find-fail');

        $artist = $manager->create('phpunit.' . randomString(12));
        $this->artistIdList[] = $artist->id;

        // fake it
        DB::DB()->prepared_query("
            INSERT INTO artist_usage
                   (artist_id, artist_role_id, uses)
            VALUES (?,         ?,              ?)
            ", $artist->id,    1,              RANDOM_ARTIST_MIN_ENTRIES
        );

        // before test run: TRUNCATE TABLE artst_usage;
        // and then: new Stats\Artists()->updateUsage();
        $this->assertEquals($artist->id, $manager->findRandom()->id, 'artist-find-random');
        $this->assertNull($manager->findByIdAndRevision($artist->id, -666), 'artist-find-revision-fail');

        $this->assertGreaterThan(0, $artist->id, 'artist-create-artist-id');
        $this->assertGreaterThan(0, $artist->aliasId(), 'artist-create-alias-id');
        $artist = $manager->findById($artist->id);
        $this->assertInstanceOf(Artist::class, $artist, 'artist-is-an-artist');

        $this->assertNull($manager->findByIdAndRevision($artist->id, -1), 'artist-is-an-unrevised-artist');
        // empty, but at least it tests the SQL
        $this->assertCount(0, $artist->tagLeaderboard(), 'artist-tag-leaderboard');
    }

    public function testArtistInfo(): void {
        $manager = new Manager\Artist();
        $artist = $manager->create('phpunit.' . randomString(12));
        $this->artistIdList[] = $artist->id;

        $this->assertEquals("<a href=\"artist.php?id={$artist->id}\">{$artist->name()}</a>", $artist->link(), 'artist-link');
        $this->assertEquals("artist.php?id={$artist->id}", $artist->location(), 'artist-location');
        $this->assertNull($artist->body(), 'artist-body-null');
        $this->assertNull($artist->image(), 'artist-image-null');
        $this->assertFalse($artist->isLocked(), 'artist-is-unlocked');
        $this->assertTrue($artist->toggleAttr('locked', true), 'artist-toggle-locked');
        $this->assertTrue($artist->isLocked(), 'artist-is-locked');
    }

    public function testArtistAPI(): void {
        $manager = new Manager\Artist();
        $artist = $manager->create('phpunit.' . randomString(12));
        $this->artistIdList[] = $artist->id;
        $_GET['req']       = 'artist';
        $_GET['artist_id'] = $artist->id;
        $this->assertEquals(
            [
                'ArtistID' => $artist->id,
                'Name'     => $artist->name(),
            ],
            new API\Artist([])->run(),
            'artist-api',
        );
    }

    public function testArtistRevision(): void {
        $manager = new Manager\Artist();
        $artist = $manager->create('phpunit.' . randomString(12));
        $this->artistIdList[] = $artist->id;

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

        $artistV1 = $manager->findByIdAndRevision($artist->id, $revision);
        $this->assertNotNull($artistV1, 'artist-revision-1-found');
        $this->assertEquals('phpunit body test', $artistV1->body(), 'artist-body-rev-1');

        $artistV2 = $manager->findByIdAndRevision($artist->id, $rev2);
        $this->assertInstanceOf(Artist::class, $artistV2, 'artist-found-by-id-and-rev');
        $this->assertEquals('https://example.com/artist-revised.jpg', $artistV2->image(), 'artist-image-rev-2');

        $list = $artist->revisionList();
        $this->assertEquals('phpunit second revision', $list[0]['summary']);
        $this->assertEquals($revision, $list[1]['revision']);

        $artist->revertRevision($revision, $this->user);
        $this->assertCount(3, $artist->revisionList());
        $this->assertEquals($artistV1->body(), $artist->body(), 'artist-body-rev-3');
    }

    public function testArtistAlias(): void {
        $manager = new Manager\Artist();
        $artist = $manager->create('phpunit.' . randomString(12));
        $this->artistIdList[] = $artist->id;

        $this->assertEquals($artist->id, $artist->id, 'artist-find-by-alias-id');
        $this->assertEquals(
            $artist->id,
            $manager->findByName($artist->name())?->id,
            'artist-find-by-alias-name'
        );
        $this->assertEquals(
            $artist->aliasId(),
            $manager->findByName($artist->name())?->aliasId(),
            'artist-find-aliasid-by-alias-name'
        );
        $this->assertEquals(
            1,
            $manager->aliasUseTotal($artist->aliasId()),
            'artist-sole-alias'
        );
        $this->assertCount(
            0,
            $manager->tgroupList($artist->aliasId(), new Manager\TGroup()),
            'artist-no-tgroup'
        );

        $aliasName = $artist->name() . '-alias';
        $newId = $artist->addAlias($aliasName, 0, $this->user);
        $this->assertEquals($artist->aliasId() + 1, $newId, 'artist-new-alias');
        $this->assertEquals(2, $manager->aliasUseTotal($artist->aliasId()), 'artist-two-alias');

        $alias = $manager->findByName($aliasName);
        $this->assertEquals($artist->id, $alias?->id, 'artist-fetch-artist-id');
        $this->assertEquals($newId, $alias?->aliasId(), 'artist-fetch-alias-id');

        $this->assertEquals(1, $artist->removeAlias($newId), 'artist-remove-alias');
    }

    public function testArtistNonRedirAlias(): void {
        $manager = new Manager\Artist();
        $artist = $manager->create('phpunit.' . randomString(12));
        $this->artistIdList[] = $artist->id;

        $aliasName = $artist->name() . '-reformed';
        $newId = $artist->addAlias($aliasName, $artist->id, $this->user);
        $this->assertEquals(
            $artist->aliasId() + 1,
            $newId,
            'artist-new-non-redirect'
        );
        $aliasInfo = $artist->aliasInfo();
        $this->assertCount(2, $aliasInfo, 'artist-alias-count');
        $aliasId = $artist->aliasId();
        $this->assertEquals(
            [
                'alias'    => [],
                'alias_id' => $aliasId,
                'name'     => $artist->name(),
                'user'     => null,
            ],
            $aliasInfo[$aliasId],
            'artist-alias-alias-id'
        );
        $artistInfo = $aliasInfo[$artist->id];
        $this->assertArrayHasKey('alias', $artistInfo, 'artist-alias-has-key');
        $alias = current($artistInfo['alias']);
        $this->assertEquals($newId, $alias['alias_id'], 'artist-alias-artist-id');
        $this->assertEquals($aliasName, $alias['name'], 'artist-alias-name');
        $this->assertEquals($this->user->id, $alias['user']->id, 'artist-alias-user');
    }

    public function testArtistMerge(): void {
        $manager = new Manager\Artist();
        $oldName = 'phpunit.artist.' . randomString(12);
        $old = $manager->create($oldName);
        $oldAliasId = $old->aliasId();
        $this->artistIdList[] = $old->id;

        $newName = 'phpunit.artist.' . randomString(12);
        $new = $manager->create($newName);
        $this->artistIdList[] = $new->id;

        $otherArtist = 'phpunit.artist.' . randomString(12);
        $other = $manager->create($otherArtist);
        $this->artistIdList[] = $other->id;

        $userBk = new User\Bookmark($this->user);
        $userBk->create($old);
        $userBk->create($other);

        $commentMan = new Manager\Comment();
        $postList = [
            $commentMan->create($this->user, 'artist', $old->id, 'phpunit merge ' . randomString(6)),
            $commentMan->create($this->user, 'artist', $new->id, 'phpunit merge ' . randomString(6)),
        ];

        $this->extra = Helper::makeUser('merge.' . randomString(10), 'merge');
        $extraBk = new User\Bookmark($this->extra);
        $extraBk->create($old);
        $extraBk->create($new);

        $collMan = new Manager\Collage();
        $this->collage = $collMan->create(
            user:        $this->user,
            categoryId:  CollageType::artist->value,
            name:        'phpunit merge artist ' . randomString(10),
            description: 'phpunit merge artist description',
            tagList:     'jazz',
        );
        $this->collage->addEntry($old, $this->user);

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
            ),
            'artist-merge-n',
        );
        $this->assertNull($manager->findById($old->id), 'art-merge-no-old');
        $this->assertTrue($userBk->isBookmarked($new), 'art-merge-user-bookmarked-new');
        $this->assertTrue($userBk->isBookmarked($other), 'art-merge-user-bookmarked-other');
        $this->assertTrue($extraBk->isBookmarked($new), 'art-merge-extra-bookmarked-new');
        $this->assertCount(1, $extraBk->artistList(), 'art-merge-extra-bookmarked-list');

        // FIXME: flushed collage objects cannot be refreshed
        $merged = $collMan->findById($this->collage->id);
        $this->assertEquals([$new->id], $merged?->entryList(), 'art-merge-collage');

        $comment = new Comment\Artist($new->id, 1, 0);
        $comment->load(); // FIXME: load() should not be necessary
        $this->assertEquals(
            [$postList[0]->id, $postList[1]->id],
            array_map(fn($p) => $p['ID'], $comment->thread()),
            'art-merge-comments'
        );

        $n = 0;
        foreach ($this->tgroupList as $tgroup) {
            ++$n;
            $artistRole = $tgroup->flush()->artistRole();
            // cannot use flush() as the aliasId is wiped, replace the object by its id
            $actual = $artistRole->idList()[ARTIST_MAIN][0];
            $actual['artist'] = $actual['artist']->id;
            $this->assertEquals(
                [
                    'id'      => $new->id,
                    'aliasid' => $oldAliasId,
                    'artist'  => $new->id,
                    'name'    => $oldName,
                ],
                $actual,
                "art-merge-ar-$n"
            );
        }
    }

    public function testArtistModify(): void {
        $manager = new Manager\Artist();
        $artist = $manager->create('phpunit.' . randomString(12));
        $this->artistIdList[] = $artist->id;

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

    public function testArtistRenameBasic(): void {
        $manager = new Manager\Artist();
        $artist = $manager->create('phpunit.' . randomString(12));
        $this->artistIdList[] = $artist->id;

        $oldName = $artist->name();
        $rename = $artist->name() . '-rename';
        $this->assertEquals(
            $artist->aliasId() + 1,
            $artist->renameAlias($artist->aliasId(), $rename, $this->user),
            'alias-rename'
        );
        $this->assertContains($rename, $artist->aliasNameList(), 'alias-is-renamed');
        $newAlias = array_filter($artist->aliasList(), fn($v) => $v['name'] == $rename)[0];
        $oldAlias = array_filter($artist->aliasList(), fn($v) => $v['name'] == $oldName)[0];
        $this->assertEquals($newAlias['alias_id'], $oldAlias['redirect_id'], 'alias-is-redirected');
    }

    public function testArtistRenamePrimary(): void {
        $manager = new Manager\Artist();
        $artist = $manager->create('phpunit.' . randomString(12));
        $this->artistIdList[] = $artist->id;

        $commentMan = new Manager\Comment();
        $post = $commentMan->create($this->user, 'artist', $artist->id, 'phpunit smart rename ' . randomString(6));

        $request = Helper::makeRequestMusic(
            user:            $this->user,
            title:           'phpunit smart rename ' . randomString(6),
            releaseType:     1,
            encoding:        new Request\Encoding(list: ['Lossless']),
            format:          new Request\Format(list: ['MP3']),
            media:           new Request\Media(list: ['CD']),
        );
        $this->assertInstanceOf(
            ArtistRole\Request::class,
            $request->artistRole(),
            'request-rename-has-artistrole'
        );
        $request->artistRole()->set(
            [ARTIST_MAIN => [$artist->name()]],
            $this->user,
            new Manager\Artist(),
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
        $artist->renameAlias($artist->primaryAliasId(), $name, $this->user);
        $this->assertEquals($name, $artist->name(), 'artist-is-smart-renamed');

        $commentPage = new Comment\Artist($artist->id, 1, 0);
        $commentPage->load();
        $threadList = $commentPage->threadList(new Manager\User());
        $this->assertCount(1, $threadList, 'artist-renamed-comments');
        $this->assertEquals($post->id, $threadList[0]['postId'], 'artist-renamed-comments');

        $request->flush();
        $this->assertInstanceOf(
            ArtistRole\Request::class,
            $request->artistRole(),
            'request-has-artistrole'
        );
        $idList = $request->artistRole()->idList();
        $this->assertEquals($artist->id, $idList[ARTIST_MAIN][0]['id'], 'artist-renamed-request');
        $request->remove();

        $this->tgroupList[0]->artistRole()->flush();
        $idList = $this->tgroupList[0]->artistRole()->idList();
        $this->assertEquals($artist->id, $idList[ARTIST_MAIN][0]['id'], 'artist-renamed-tgroup');
    }

    public function testRenameAliasCapchange(): void {
        $artistMan = new Manager\Artist();
        $mainName = 'phpunit.' . randomString(12);
        $artist = $artistMan->create($mainName);
        $mainAliasId = $artist->aliasId();
        $aliasName = 'phpunit.' . randomString(12) . '-alias';
        $artist->addAlias($aliasName, null, $this->user);

        $aliasUpper = strtoupper($aliasName);
        $aliasId = $artist->getAlias($aliasName);
        $this->assertNotNull($aliasId, 'artist-rename-capchange-create');
        $this->assertEquals(
            $aliasId,
            $artist->renameAlias($aliasId, $aliasUpper, $this->user),
            'artist-rename-capchange-1',
        );
        $this->assertEquals(
            $aliasId,
            $artist->getAlias($aliasName),
            'artist-rename-capchange-2',
        );
        $this->assertEquals(
            $mainAliasId,
            $artist->primaryAliasId(),
            'artist-rename-capchange-sanity',
        );
    }

    public function testRenameAliasNraMerge(): void {
        $artistMan = new Manager\Artist();
        $mainName = 'phpunit.' . randomString(12);
        $artist = $artistMan->create($mainName);
        $mainAliasId = $artist->aliasId();

        // create NRA
        $a1Name = 'phpunit.' . randomString(12) . '-alias';
        $a1Id = $artist->addAlias($a1Name, null, $this->user);

        // add RA
        $raAliasName = $a1Name . '-raalias';
        $raId = $artist->addAlias($raAliasName, $a1Id, $this->user);

        // create second NRA
        $a2Name = 'phpunit.' . randomString(12) . '-alias';
        $a2Id = $artist->addAlias($a2Name, null, $this->user);

        $this->assertEquals(
            $a2Id,
            $artist->renameAlias($a1Id, $a2Name, $this->user),
            'artist-rename-nramerge-1',
        );

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
        $a3Id = $artist->renameAlias($a2Id, $a2NewName, $this->user);
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
        $manager = new Manager\Artist();
        $artist = $manager->create('phpunit.artsim.' . randomString(12));
        $this->artistIdList[] = $artist->id;
        $this->extra = Helper::makeUser('art2.' . randomString(10), 'artist');

        $other1 = $manager->create('phpunit.other1.' . randomString(12));
        $other2 = $manager->create('phpunit.other2.' . randomString(12));

        $this->artistIdList[] = $other1->id;
        $this->artistIdList[] = $other2->id;

        $this->assertFalse($artist->similar()->voteSimilar($this->extra, $artist, true), 'artist-vote-self');

        $this->assertEquals(1, $artist->similar()->addSimilar($other1, $this->user), 'artist-add-other1');
        $this->assertEquals(0, $artist->similar()->addSimilar($other1, $this->user), 'artist-read-other1');
        $this->assertEquals(1, $artist->similar()->addSimilar($other2, $this->user), 'artist-add-other2');
        $this->assertEquals(1, $other1->similar()->addSimilar($other2, $this->user), 'artist-other1-add-other2');

        $this->assertTrue($artist->similar()->voteSimilar($this->extra, $other1, true), 'artist-vote-up-other1');
        $this->assertFalse($artist->similar()->voteSimilar($this->extra, $other1, true), 'artist-revote-up-other1');
        $this->assertTrue($other1->similar()->voteSimilar($this->extra, $other2, false), 'artist-vote-down-other2');

        $this->assertEquals(
            [
                [
                    'artist_id'  => $other1->id,
                    'name'       => $other1->name(),
                    'score'      => 300,
                    'similar_id' => $artist->similar()->findSimilarId($other1),
                ],
                [
                    'artist_id'  => $other2->id,
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
            [$other1->id, $other2->id],
            array_values(array_map(fn($sim) => $sim['artist_id'], $graph)),
            'artist-similar-id-list'
        );
        $this->assertEquals($other2->id, $graph[$other1->id]['related'][0], 'artist-sim-related');
        $this->assertLessThan($graph[$other1->id]['proportion'], $graph[$other2->id]['proportion'], 'artist-sim-proportion');

        $this->assertFalse($artist->similar()->removeSimilar($artist, $this->extra), 'artist-remove-similar-self');
        $this->assertTrue($artist->similar()->removeSimilar($other2, $this->extra), 'artist-remove-other');
        $this->assertFalse($artist->similar()->removeSimilar($other2, $this->extra), 'artist-re-remove-other');
    }

    public function testArtistJson(): void {
        $manager = new Manager\Artist();
        $artist = $manager->create('phpunit.' . randomString(12));
        $this->artistIdList[] = $artist->id;

        $other = $manager->create('phpunit.other1.' . randomString(12));
        $this->artistIdList[] = $other->id;
        $artist->similar()->addSimilar($other, $this->user);

        $this->tgroupList = [
            Helper::makeTGroupMusic(
                $this->user,
                'phpunit json ' . randomString(10),
                [[ARTIST_MAIN], [$artist->name()]],
                ['metal'],
            ),
        ];
        Helper::makeTorrentMusic(
            tgroup:          $this->tgroupList[0],
            user:            $this->user,
            catalogueNumber: 'UA-JSON-1',
        );

        $json = new Json\Artist(
            $artist,
            $this->user,
            new User\Bookmark($this->user),
        );
        $payload = $json->payload();
        $this->assertEquals(
            ["id", "name", "notificationsEnabled", "hasBookmarked", "image",
                "body", "bodyBbcode", "vanityHouse", "tags", "similarArtists",
                "statistics", "torrentgroup", "requests"],
            array_keys($payload),
            'artist-json-payload'
        );
        $this->assertEquals($artist->id, $payload['id'], 'artist-payload-id');
        $this->assertEquals($artist->name(), $payload['name'], 'artist-payload-name');
        $this->assertCount(1, $payload['tags'], 'artist-payload-tags');
        $this->assertCount(1, $payload['similarArtists'], 'artist-payload-similar-artists');
        $this->assertCount(1, $payload['torrentgroup'], 'artist-payload-torrentgroup');
        $this->assertCount(0, $payload['requests'], 'artist-payload-requests');
        $this->assertIsArray($payload['statistics'], 'artist-payload-statistics');

        $similar = new Json\ArtistSimilar($artist, 1)->payload();
        $this->assertCount(1, $similar, 'artist-similar-payload-total');
        $this->assertEquals($other->id, $similar[0]['artist_id'], 'artist-similar-payload-id');
    }

    public function testArtistAutocomplete(): void {
        $manager = new Manager\Artist();
        $composer = null;
        $prefix2 = 'qac' . randomString(5) . '.';
        $prefix4 = $prefix2 . 'aa';
        foreach ([
            $prefix4,
            $prefix2 . 'ab',
            $prefix2 . 'ba',
            'zac' . randomString(5) . '.off',
        ] as $name) {
            if (is_null($composer)) {
                $composer = $name;
            }
            $artist = $manager->create($name);
            $this->artistIdList[] = $artist->id;
            $tgroup = Helper::makeTGroupMusic(
                $this->user,
                'phpunit artist autocomp ' . randomString(10),
                [[ARTIST_MAIN], [$name]],
                ['punk'],
            );
            Helper::makeTorrentMusic($tgroup, $this->user);
            $this->tgroupList[] = $tgroup;
        }
        $this->tgroupList[0]
            ->addArtists([ARTIST_COMPOSER], [$composer]);
        global $Cache;
        $Cache->delete_multi([
            $manager->autocompleteKey("%"),
            $manager->autocompleteKey($prefix2),
            $manager->autocompleteKey($prefix4),
        ]);
        $this->assertCount(0, $manager->autocompleteList("%"), 'artist-autocomp-wildcard');
        $this->assertCount(3, $manager->autocompleteList($prefix2), 'artist-autocomp-2');
        $this->assertCount(1, $manager->autocompleteList($prefix4), 'artist-autocomp-4');

        $artist = $manager->create('qredir.phpunit.' . randomString(6));
        $this->artistIdList[] = $artist->id;
        $redirectName = $artist->name() . '-typo';
        $artist->addAlias($redirectName, $artist->aliasId(), $this->user);
        $tgroup = Helper::makeTGroupMusic(
            $this->user,
            'phpunit artist autocomp redirect ' . randomString(10),
            [[ARTIST_MAIN], [$artist->primaryName()]],
            ['punk'],
        );
        Helper::makeTorrentMusic($tgroup, $this->user);
        $this->tgroupList[] = $tgroup;
        $Cache->delete_multi([
            $manager->autocompleteKey($redirectName),
        ]);
        $autocomplete = $manager->autocompleteList($redirectName);
        $this->assertCount(1, $autocomplete, 'artist-autocomp-redir-count');
        $this->assertEquals([$artist->id], array_column($autocomplete, 'data'), 'artist-autocomp-redir-id');
        $this->assertEquals([$redirectName], array_column($autocomplete, 'value'), 'artist-autocomp-redir-value');
        $widget = $this->renderAutocompleteWidget($autocomplete[0]);
        $this->assertStringContainsString(
            "<a class=\"cae-row-link\" href=\"artist.php?id={$artist->id}\"",
            $widget,
            'artist-autocomp-redir-html-link'
        );
        $this->assertStringContainsString(
            "<div class=\"cae-name\">$redirectName</div>",
            $widget,
            'artist-autocomp-redir-html-name'
        );
        $this->assertStringNotContainsString(
            "<div class=\"cae-name\">{$artist->primaryName()}</div>",
            $widget,
            'artist-autocomp-redir-html-name-not-primary'
        );
        $this->assertStringContainsString(
            "<div class=\"cae-aka\">aka {$artist->primaryName()}</div>",
            $widget,
            'artist-autocomp-redir-html-aka'
        );
        $cached = $manager->autocompleteList($redirectName);
        $this->assertCount(1, $cached, 'artist-autocomp-redir-cache-count');
        $this->assertSame($autocomplete[0]['data'], $cached[0]['data'], 'artist-autocomp-redir-cache-data');
        $this->assertSame($autocomplete[0]['value'], $cached[0]['value'], 'artist-autocomp-redir-cache-value');
        $this->assertInstanceOf(Artist::class, $cached[0]['widget']['artist'], 'artist-autocomp-redir-cache-artist-object');
        $this->assertSame(
            $widget,
            $this->renderAutocompleteWidget($cached[0]),
            'artist-autocomp-redir-cache-html'
        );
    }

    public function testArtistAutocompleteSortBySnatchTotal(): void {
        $manager = new Manager\Artist();
        $rankPrefix = 'qrank.phpunit.' . randomString(6) . '.';
        $rankedList = [];
        foreach ([
            ['Alpha', 20],
            ['Beta', 50],
            ['Gamma', 20],
        ] as [$suffix, $snatches]) {
            $name = $rankPrefix . $suffix;
            $artist = $manager->create($name);
            $this->artistIdList[] = $artist->id;
            $tgroup = Helper::makeTGroupMusic(
                $this->user,
                'phpunit artist autocomp rank ' . randomString(10),
                [[ARTIST_MAIN], [$name]],
                ['punk'],
            );
            $torrent = Helper::makeTorrentMusic($tgroup, $this->user);
            Helper::addTorrentTraffic($torrent, 0, 0, $snatches);
            $this->tgroupList[] = $tgroup;
            $rankedList[$suffix] = $artist;
        }
        global $Cache;
        $Cache->delete_value($manager->autocompleteKey($rankPrefix));
        $autocomplete = $manager->autocompleteList($rankPrefix);
        $this->assertSame(
            [
                $rankedList['Beta']->id,
                $rankedList['Alpha']->id,
                $rankedList['Gamma']->id,
            ],
            array_column($autocomplete, 'data'),
            'artist-autocomp-order-snatched'
        );
        $this->assertSame(
            [
                $rankPrefix . 'Beta',
                $rankPrefix . 'Alpha',
                $rankPrefix . 'Gamma',
            ],
            array_column($autocomplete, 'value'),
            'artist-autocomp-order-snatched-value'
        );
    }

    public function testArtistAutocompleteFlushesCachedHtmlOnRevisionChange(): void {
        $manager = new Manager\Artist();
        $name = 'qcache.phpunit.' . randomString(6);
        $prefix = substr($name, 0, 6);
        $artist = $manager->create($name);
        $this->artistIdList[] = $artist->id;
        $artist->createRevision(
            body:    'before cache body',
            image:   null,
            summary: ['before cache body'],
            user:    $this->user,
        );
        $tgroup = Helper::makeTGroupMusic(
            $this->user,
            'phpunit artist autocomp cache ' . randomString(10),
            [[ARTIST_MAIN], [$name]],
            ['punk'],
        );
        Helper::makeTorrentMusic($tgroup, $this->user);
        $this->tgroupList[] = $tgroup;

        global $Cache;
        $Cache->delete_value($manager->autocompleteKey($prefix));
        $autocomplete = $manager->autocompleteList($prefix);
        $this->assertStringContainsString(
            'before cache body',
            $this->renderAutocompleteWidget($autocomplete[0]),
            'artist-autocomp-cache-before'
        );

        $artist->createRevision(
            body:    'after cache body',
            image:   null,
            summary: ['after cache body'],
            user:    $this->user,
        );
        $autocomplete = $manager->autocompleteList($prefix);
        $widget = $this->renderAutocompleteWidget($autocomplete[0]);
        $this->assertStringContainsString('after cache body', $widget, 'artist-autocomp-cache-after');
        $this->assertStringNotContainsString('before cache body', $widget, 'artist-autocomp-cache-stale');
    }

    public function testArtistBookmark(): void {
        $name = 'phpunit.' . randomString(12);
        $manager = new Manager\Artist();
        $artist = $manager->create($name);
        $this->artistIdList[] = $artist->id;

        new User\Bookmark($this->user)->create($artist);
        $json = new Json\Bookmark\Artist(new User\Bookmark($this->user));
        $this->assertEquals(
            [[
                'artistId'   => $artist->id,
                'artistName' => $name,
            ]],
            $json->payload(),
            'artist-json-bookmark-payload'
        );
    }

    public function testArtistDiscogs(): void {
        $manager = new Manager\Artist();
        $artist = $manager->create('phpunit.' . randomString(12));
        $this->artistIdList[] = $artist->id;

        $id = (int)DB::DB()->scalar("
            SELECT 1+coalesce(max(artist_discogs_id), 0) FROM artist_discogs
        ");
        $name = 'discogs phpunit ' . randomString(10);
        $discogs = new Util\Discogs(
            id: $id,
            stem: $name,
            name: $name,
            sequence: 2,
        );
        $this->assertEquals($id, $discogs->id, 'artist-discogs-id');
        $this->assertEquals($name, $discogs->name(), 'artist-discogs-name');
        $this->assertEquals($name, $discogs->stem(), 'artist-discogs-stem');
        $this->assertEquals(2, $discogs->sequence(), 'artist-discogs-sequence');
        $this->assertTrue(
            $artist->setField('discogs', $discogs)->setUpdateUser($this->user)->modify(),
            'artist-add-discogs'
        );
        $this->assertEquals($name, $artist->discogs()->name(), 'artist-self-discogs-name');
        $context = $artist->widgetContext();
        $this->assertSame($artist, $context['artist'], 'artist-widget-context-object');
        $widget = \Gazelle\Util\Twig::factory(new Manager\User())->render(
            'artist/widget.twig',
            $context
        );
        $this->assertStringContainsString('cae-advanced-meta', $widget, 'artist-discogs-advanced-meta');
        $this->assertStringContainsString('cae-advanced-item-discogs', $widget, 'artist-discogs-advanced-discogs');
        $this->assertStringContainsString((string)$id, $widget, 'artist-discogs-advanced-discogs-id');
        $this->assertStringContainsString('cae-advanced-item-snatches', $widget, 'artist-discogs-advanced-snatches');
        $this->assertEquals(1, $artist->removeDiscogsRelation(), 'artist-discogs-remove');
    }

    public function testArtistRole(): void {
        $manager = new Manager\Artist();
        $this->assertTrue($manager->roleExists(0, ARTIST_MAIN), 'artist-role-main');
        $this->assertFalse($manager->roleExists(0, 99), 'artist-role-inexistent');
    }

    public function testArtistComment(): void {
        $manager = new Manager\Artist();
        $artist = $manager->create('phpunit.' . randomString(12));
        $this->artistIdList[] = $artist->id;
        $body = "phpunit u{$this->user->id} a{$artist->id}";
        $comment = new Manager\Comment()->create(
            $this->user,
            CommentType::artist->value,
            $artist->id,
            $body
        );

        $search = new Search\Comment(
            CommentType::artist,
            'default',
            $this->user->id === $this->user->id,
            $this->user,
        );

        $this->assertCount(0, $search->linkList(), 'artist-comment-linklist');
        $this->assertEquals(
            'Artist comments',
            $search->title(),
            'artist-comment-linklist'
        );
        $this->assertEquals(
            1,
            $search->total(),
            'artist-comment-total'
        );
        $page = $search->page(2, 0);
        $this->assertCount(1, $page['list'], 'artist-comment-list-total');
        $this->assertCount(1, $page['user'], 'artist-comment-user-total');
        $this->assertTrue(
            isset($page['user'][$this->user->id]),
            'artist-comment-user-id'
        );
        $comment = $page['list'][0];
        $this->assertEqualsCanonicalizing(
            ["id_user", "type", "id_type", "name", "id", "body", "created", "edited", "id_editor"],
            array_keys($comment),
            'artist-comment-keys',
        );
        $this->assertEquals($body, $comment['body'], 'artist-comment-body');
    }
}
