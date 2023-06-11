<?php

use \PHPUnit\Framework\TestCase;

require_once(__DIR__ . '/../../lib/bootstrap.php');
require_once(__DIR__ . '/../helper.php');

class ArtistTest extends TestCase {
    protected Gazelle\Artist $artist;
    protected Gazelle\User   $user;
    protected Gazelle\User   $voter;
    protected array          $artistIdList = [];

    public function setUp(): void {
        $this->user = Helper::makeUser('arty.' . randomString(10), 'artist');
    }

    public function tearDown(): void {
        $logger  = new \Gazelle\Log;
        $manager = new \Gazelle\Manager\Artist;
        foreach ($this->artistIdList as $artistId) {
            $artist = $manager->findById($artistId);
            if ($artist) {
                $artist->toggleAttr('locked', false);
                $artist->remove($this->user, $logger);
            }
        }
        if (isset($this->voter)) {
            $this->voter->remove();
        }
        $this->user->remove();
    }

    public function testArtistCreate(): void {
        $manager = new \Gazelle\Manager\Artist;
        $this->assertNull($manager->findById(-666), 'artist-find-fail');

        [$artistId, $aliasId] = $manager->create('phpunit.' . randomString(12));
        $this->artistIdList[] = $artistId;

        (Gazelle\DB::DB())->prepared_query("
            INSERT INTO artist_usage
                   (artist_id, role, uses)
            VALUES (?,         ?,    ?)
            ", $artistId, '1', RANDOM_ARTIST_MIN_ENTRIES
        );
        // If the following test fails locally:
        // before test run: TRUNCATE TABLE artist_usage;
        // afte test run: (new \Gazelle\Stats\Artists)->updateUsage();
        $this->assertEquals($artistId, $manager->findRandom()->id(), 'artist-find-random');
        $this->assertNull($manager->findByIdAndRevision($artistId, -666), 'artist-find-revision-fail');

        $this->assertGreaterThan(0, $artistId, 'artist-create-artist-id');
        $this->assertGreaterThan(0, $aliasId, 'artist-create-alias-id');
        $artist = $manager->findById($artistId);
        $this->assertInstanceOf(\Gazelle\Artist::class, $artist, 'artist-is-an-artist');
        $this->assertEquals([$artistId, $aliasId], $manager->fetchArtistIdAndAliasId($artist->name()), 'artist-fetch-artist');

        $this->assertNull($manager->findByIdAndRevision($artistId, -1), 'artist-is-an-unrevised-artist');
        // empty, but at least it tests the SQL
        $this->assertCount(0, $artist->tagLeaderboard(), 'artist-tag-leaderboard');
    }

    public function testArtistInfo(): void {
        $manager = new \Gazelle\Manager\Artist;
        [$artistId, $aliasId] = $manager->create('phpunit.' . randomString(12));
        $artist = $manager->findById($artistId);
        $this->artistIdList[] = $artist->id();

        $this->assertEquals("<a href=\"artist.php?id={$artist->id()}\">{$artist->name()}</a>", $artist->link(), 'artist-link');
        $this->assertEquals("artist.php?id={$artist->id()}", $artist->location(), 'artist-location');
        $this->assertEquals('artists_group', $artist->tableName(), 'artist-table-name');
        $this->assertEquals('ArtistID', $artist->pkName(), 'artist-table-pk');
        $this->assertNull($artist->body(), 'artist-body-null');
        $this->assertNull($artist->image(), 'artist-image-null');
        $this->assertFalse($artist->isLocked(), 'artist-is-unlocked');
        $this->assertTrue($artist->toggleAttr('locked', true), 'artist-toggle-locked');
        $this->assertTrue($artist->isLocked(), 'artist-is-locked');
    }

    public function testArtistRevision(): void {
        $manager = new \Gazelle\Manager\Artist;
        [$artistId, $aliasId] = $manager->create('phpunit.' . randomString(12));
        $artist = $manager->findById($artistId);
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

        $artistV1 = $manager->findByIdAndRevision($artistId, $revision);
        $this->assertNotNull($artistV1, 'artist-revision-1-found');
        $this->assertEquals('phpunit body test', $artistV1->body(), 'artist-body-rev-1');

        $artistV2 = $manager->findByIdAndRevision($artistId, $rev2);
        $this->assertEquals('https://example.com/artist-revised.jpg', $artistV2->image(), 'artist-image-rev-2');

        $list = $artist->revisionList();
        $this->assertEquals('phpunit second revision', $list[0]['summary']);
        $this->assertEquals($revision, $list[1]['revision']);

        $rev3 = $artist->revertRevision($revision, $this->user);
        $this->assertCount(3, $artist->revisionList());
        $this->assertEquals($artistV1->body(), $artist->body(), 'artist-body-rev-3');
    }

    public function testArtistAlias(): void {
        $manager = new \Gazelle\Manager\Artist;
        $logger  = new \Gazelle\Log;
        [$artistId, $aliasId] = $manager->create('phpunit.' . randomString(12));
        $artist = $manager->findByAliasId($aliasId);
        $this->artistIdList[] = $artist->id();

        $this->assertEquals($artistId, $artist->id(), 'artist-find-by-alias');
        $this->assertEquals(1, $manager->aliasUseTotal($aliasId), 'artist-sole-alias');
        $this->assertCount(0, $manager->tgroupList($aliasId, new Gazelle\Manager\TGroup), 'artist-no-tgroup');

        $aliasName = $artist->name() . '-alias';
        $newId = $artist->addAlias($aliasName, 0, $this->user, $logger);
        $this->assertEquals($aliasId + 1, $newId, 'artist-new-alias');
        $this->assertEquals(2, $manager->aliasUseTotal($aliasId), 'artist-two-alias');

        [$fetchArtistId, $fetchAliasId] = $manager->fetchArtistIdAndAliasId($aliasName);
        $this->assertEquals($artistId, $fetchArtistId, 'artist-fetch-artist-id');
        $this->assertEquals($newId, $fetchAliasId, 'artist-fetch-alias-id');

        $this->assertEquals(1, $artist->removeAlias($newId, $this->user, $logger), 'artist-remove-alias');
    }

    public function testArtistNonRedirAlias(): void {
        $manager = new \Gazelle\Manager\Artist;
        $logger  = new \Gazelle\Log;
        [$artistId, $aliasId] = $manager->create('phpunit.' . randomString(12));
        $artist = $manager->findByAliasId($aliasId);
        $this->artistIdList[] = $artist->id();

        $aliasName = $artist->name() . '-reformed';
        $newId = $artist->addAlias($aliasName, $artistId, $this->user, $logger);
        $this->assertEquals($aliasId + 1, $newId, 'artist-new-non-redirect');
    }

    public function testArtistModify(): void {
        $manager = new \Gazelle\Manager\Artist;
        [$artistId, $aliasId] = $manager->create('phpunit.' . randomString(12));
        $artist = $manager->findById($artistId);
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
        $manager = new \Gazelle\Manager\Artist;
        [$artistId, $aliasId] = $manager->create('phpunit.' . randomString(12));
        $artist = $manager->findById($artistId);
        $this->artistIdList[] = $artist->id();

        $rename = $artist->name() . '-rename';
        $this->assertEquals(
            $aliasId + 1,
            $artist->rename($aliasId, $rename, new Gazelle\Manager\Request, $this->user),
            'artist-rename'
        );
        $this->assertEquals($rename, $artist->name(), 'artist-is-renamed');
    }

    public function testArtistSimilar(): void {
        $manager = new \Gazelle\Manager\Artist;
        [$artistId, $aliasId] = $manager->create('phpunit.' . randomString(12));
        $artist = $manager->findById($artistId);
        $this->artistIdList[] = $artist->id();
        $this->voter = Helper::makeUser('art2.' . randomString(10), 'artist');

        $this->assertFalse($artist->voteSimilar($this->voter, $artist->id(), true), 'artist-vote-self');

        [$sim1Id, $sim1aliasId] = $manager->create('phpunit.' . randomString(12));
        $sim1 = $manager->findById($sim1Id);
        $this->artistIdList[] = $sim1->id();
        [$sim2Id, $sim2aliasId] = $manager->create('phpunit.' . randomString(12));
        $sim2 = $manager->findById($sim2Id);
        $this->artistIdList[] = $sim2->id();

        $this->assertEquals(1, $artist->addSimilar($sim1, $this->user), 'artist-add-sim1');
        $this->assertEquals(0, $artist->addSimilar($sim1, $this->user), 'artist-read-sim1');
        $this->assertEquals(1, $artist->addSimilar($sim2, $this->user), 'artist-add-sim2');
        $this->assertEquals(1, $sim1->addSimilar($sim2, $this->user), 'artist-sim1-add-sim2');

        $this->assertTrue($artist->voteSimilar($this->voter, $sim1->id(), true), 'artist-vote-up-sim1');
        $this->assertFalse($artist->voteSimilar($this->voter, $sim1->id(), true), 'artist-revote-up-sim1');
        $this->assertTrue($sim1->voteSimilar($this->voter, $sim2->id(), false), 'artist-vote-down-sim2');

        $graph = $artist->similarGraph(100, 100);
        $this->assertCount(2, $graph, 'artist-similar-count');
        $this->assertEquals(
            [$sim1Id, $sim2Id],
            array_values(array_map(fn($sim) => $sim['artist_id'], $graph)),
            'artist-similar-id-list'
        );
        $this->assertEquals(400, $graph[$sim1Id]['score'], 'artist-sim1-score');
        $this->assertEquals(200, $graph[$sim2Id]['score'], 'artist-sim2-score');
        $this->assertEquals($sim2Id, $graph[$sim1Id]['related'][0], 'artist-sim-related');
        $this->assertLessThan($graph[$sim1Id]['proportion'], $graph[$sim2Id]['proportion'], 'artist-sim-proportion');
    }

    public function testArtistJson(): void {
        $manager = new \Gazelle\Manager\Artist;
        [$artistId, $aliasId] = $manager->create('phpunit.' . randomString(12));
        $artist = $manager->findById($artistId);
        $this->artistIdList[] = $artist->id();

        $json = (new \Gazelle\Json\Artist(
            $artist,
            $this->user,
            new Gazelle\User\Bookmark($this->user),
            new Gazelle\Manager\Request,
            new Gazelle\Manager\TGroup,
            new Gazelle\Manager\Torrent,
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
}
