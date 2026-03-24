<?php

namespace Gazelle;

use PHPUnit\Framework\TestCase;
use GazelleUnitTest\Helper;

class BetterArtistTest extends TestCase {
    protected Artist $artist;
    protected User   $user;

    public function setUp(): void {
        $this->user = Helper::makeUser('torrent.' . randomString(10), 'bettertranscode');
        $this->user->requestContext()->setViewer($this->user);
    }

    public function tearDown(): void {
        $this->artist->remove();
        Helper::removeUser($this->user);
    }

    public function testBetterArtistDescription(): void {
        $artistMan = new Manager\Artist();
        $name      = 'phpunit better ' . randomString(6);
        $this->artist = $artistMan->create($name);
        $artistId = $this->artist->id;

        $better = new Better\ArtistDescription(
            $this->user,
            Enum\BetterFilter::any,
            $artistMan
        );
        $this->assertEquals(
            'artist',
            $better->mode(),
            'better-artist-description-mode'
        );
        $this->assertGreaterThan(
            10,
            strlen($better->heading()),
            'better-artist-description-heading',
        );
        $better->setSearch($name);
        $total = $better->total();
        $this->assertEquals(
            1,
            $total,
            'better-artist-description-any',
        );
        $this->assertEquals(
            [$this->artist],
            $better->page($total, 0),
            'better-artist-description-page',
        );

        global $Cache;
        $snatched = new Better\ArtistDescription($this->user, Enum\BetterFilter::snatched, $artistMan);
        $Cache->delete_value($snatched->totalCacheKey());
        $this->assertEquals(
            0,
            $snatched->total(),
            'better-artist-description-snatched-total',
        );

        $uploaded = new Better\ArtistDescription($this->user, Enum\BetterFilter::uploaded, $artistMan);
        $Cache->delete_value($uploaded->totalCacheKey());
        $this->assertEquals(
            0,
            $uploaded->total(),
            'better-artist-description-uploaded-total',
        );
    }

    public function testBetterArtistDiscogs(): void {
        $artistMan = new Manager\Artist();
        $name      = 'phpunit better ' . randomString(6);
        $this->artist = $artistMan->create($name);
        $artistId = $this->artist->id;

        $discogs = new Better\ArtistDiscogs($this->user, Enum\BetterFilter::any, $artistMan);
        $this->assertGreaterThan(
            10,
            strlen($discogs->heading()),
            'better-artist-discogs-heading',
        );
        $discogs->setSearch($name);
        $total = $discogs->total();
        $this->assertEquals(
            1,
            $total,
            'better-artist-discogs-any',
        );
        $this->assertEquals(
            [$this->artist],
            $discogs->page($total, 0),
            'better-artist-discogs-page',
        );
    }

    public function testBetterArtistImage(): void {
        $artistMan = new Manager\Artist();
        $name      = 'phpunit better ' . randomString(6);
        $this->artist = $artistMan->create($name);
        $artistId = $this->artist->id;

        $better = new Better\ArtistImage($this->user, Enum\BetterFilter::any, $artistMan);
        $this->assertEquals(
            'artist',
            $better->mode(),
            'better-artist-image-mode',
        );
        $this->assertGreaterThan(
            10,
            strlen($better->heading()),
            'better-artist-image-heading',
        );
        $better->setSearch($name);
        $total = $better->total();
        $this->assertEquals(
            1,
            $total,
            'better-artist-image-all',
        );
        $this->assertEquals(
            [$this->artist],
            $better->page($total, 0),
            'better-artist-image-page',
        );
    }

    public function testBetterArtistCollage(): void {
        $collage = new Manager\Collage()->create(
            $this->user,
            Enum\CollageType::artist->value,
            'phpunit better ' . randomString(6),
            'phpunit description',
            'phpunit'
        );
        $artistMan = new Manager\Artist();
        $this->artist = $artistMan->create('phpunit better ' . randomString(6));
        $collage->addEntry($this->artist, $this->user);

        $better = new Better\ArtistCollage($this->user, Enum\BetterFilter::any, $artistMan);
        $this->assertGreaterThan(
            10,
            strlen($better->heading()),
            'better-collage-artist-heading',
        );
        $total  = $better->total();
        $this->assertGreaterThanOrEqual(1, $total, 'better-collage-artist-all');
        $this->assertEquals(
            [$this->artist],
            array_values(array_filter(
                $better->page($total, 0),
                fn ($a) => $a->id === $this->artist->id,
            )),
            'better-collage-artist-page',
        );
        $collage->hardRemove();
    }
}
