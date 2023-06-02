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
                $artist->remove($this->user, $logger);
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
}
