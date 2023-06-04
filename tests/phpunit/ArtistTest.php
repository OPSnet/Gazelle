<?php

use \PHPUnit\Framework\TestCase;

require_once(__DIR__ . '/../../lib/bootstrap.php');
require_once(__DIR__ . '/../helper.php');

class ArtistTest extends TestCase {
    protected Gazelle\Artist $artist;
    protected Gazelle\User   $user;
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
                // $artist->remove($this->user, $logger);
            }
        }
        $this->user->remove();
    }

    public function testArtistCreate(): void {
        $manager = new \Gazelle\Manager\Artist;
        $this->assertNull($manager->findById(-666), 'artist-find-fail');

        [$artistId, $aliasId] = $manager->create('phpunit.' . randomString(12));
        $this->artistIdList[] = $artistId;
        $this->assertNull($manager->findByIdAndRevision($artistId, -666), 'artist-find-revision-fail');

        $this->assertGreaterThan(0, $artistId, 'artist-create-artist-id');
        $this->assertGreaterThan(0, $aliasId, 'artist-create-alias-id');
        $artist = $manager->findById($artistId);
        $this->assertInstanceOf(\Gazelle\Artist::class, $artist, 'artist-is-an-artist');
        $this->assertEquals([$artistId, $aliasId], $manager->fetchArtistIdAndAliasId($artist->name()), 'artist-fetch-artist');
        $this->assertNull($manager->findByIdAndRevision($artistId, -1), 'artist-is-an-unrevised-artist');
    }

    public function testArtistInfo(): void {
        $manager = new \Gazelle\Manager\Artist;
        [$artistId, $aliasId] = $manager->create('phpunit.' . randomString(12));
        $this->artistIdList[] = $artistId;

        $artist = $manager->findById($artistId);
        $this->assertEquals("<a href=\"artist.php?id={$artist->id()}\">{$artist->name()}</a>", $artist->link(), 'artist-link');
        $this->assertEquals("artist.php?id={$artist->id()}", $artist->location(), 'artist-location');
        $this->assertEquals('artists_group', $artist->tableName(), 'artist-table-name');
        $this->assertNull($artist->body(), 'artist-body-null');
        $this->assertNull($artist->image(), 'artist-image-null');
        $this->assertFalse($artist->isLocked(), 'artist-is-unlocked');
        $this->assertTrue($artist->toggleAttr('locked', true), 'artist-toggle-locked');
        $this->assertTrue($artist->isLocked(), 'artist-is-locked');
    }

    public function testArtistRevision(): void {
        $manager = new \Gazelle\Manager\Artist;
        [$artistId, $aliasId] = $manager->create('phpunit.' . randomString(12));
        $this->artistIdList[] = $artistId;
        $artist = $manager->findById($artistId);

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

    public function testArtistModify(): void {
        $manager = new \Gazelle\Manager\Artist;
        [$artistId, $aliasId] = $manager->create('phpunit.' . randomString(12));
        $this->artistIdList[] = $artistId;
        $artist = $manager->findById($artistId);

        $this->assertTrue(
            $artist->setUpdate('body', 'body modification')->setUpdateUser($this->user)->modify(),
            'artist-modify-body'
        );
        $this->assertCount(1, $artist->revisionList());
        $this->assertTrue(
            $artist->setUpdate('VanityHouse', true)->setUpdateUser($this->user)->modify(),
            'artist-modify-showcase'
        );
        $this->assertCount(1, $artist->revisionList());

        $this->assertTrue(
            $artist ->setUpdate('image', 'https://example.com/update.png')
                ->setUpdate('summary', 'You look nice in a suit')
                ->setUpdateUser($this->user)
                ->modify(),
            'artist-modify-image'
        );
        $this->assertCount(2, $artist->revisionList());
    }
}
