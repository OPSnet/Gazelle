<?php

namespace Gazelle;

use PHPUnit\Framework\TestCase;
use GazelleUnitTest\Helper;

class BetterTGroupTest extends TestCase {
    protected TGroup $tgroup;
    protected User   $user;

    public function setUp(): void {
        $this->user = Helper::makeUser('torrent.' . randomString(10), 'bettertranscode');
        $this->user->requestContext()->setViewer($this->user);
    }

    public function tearDown(): void {
        Helper::removeTGroup($this->tgroup, $this->user);
        Helper::removeUser($this->user);
    }

    public function testBetterArtwork(): void {
        global $Cache;
        $tgMan = new Manager\TGroup();
        $snatched = new Better\Artwork($this->user, Enum\BetterFilter::snatched, $tgMan);
        $this->assertGreaterThan(
            10,
            strlen($snatched->heading()),
            'better-artwork-snatched-heading',
        );
        $Cache->delete_value($snatched->totalCacheKey());
        $this->assertEquals(0, $snatched->total(), 'better-artwork-snatched-total');

        $uploaded = new Better\Artwork($this->user, Enum\BetterFilter::uploaded, $tgMan);
        $Cache->delete_value($uploaded->totalCacheKey());
        $this->assertEquals(
            0,
            $uploaded->total(),
            'better-artwork-uploaded-total'
        );

        $artwork = new Better\Artwork($this->user, Enum\BetterFilter::any, $tgMan);
        $Cache->delete_value($artwork->totalCacheKey());
        $initialTotal = $artwork->total();
        $this->assertGreaterThanOrEqual(
            0,
            $initialTotal,
            'better-artwork-initial'
        );

        $this->tgroup = Helper::makeTGroupMusic(
            user: $this->user,
            name: 'phpunit better artwork ' . randomString(6),
            artistName: [[ARTIST_MAIN], ['phpunit better ' . randomString(12)]],
            tagName: ['bop'],
            image: '',
        );
        $Cache->delete_value($artwork->totalCacheKey());
        $this->assertEquals(
            1,
            $artwork->total() - $initialTotal,
            'better-artwork-total'
        );
    }

    public function testBetterBad(): void {
        $torMan = new Manager\Torrent();
        $badFile = new Better\Bad($this->user, Enum\BetterFilter::uploaded, $torMan);
        $this->assertEquals('torrent', $badFile->mode(), 'better-badfile-mode');
        $badFile->setBadType(Enum\TorrentFlag::badFile);
        $this->assertEquals(
            Enum\TorrentFlag::badFile,
            $badFile->torrentFlag(),
            'better-badfile-torrentflag',
        );
        $this->assertGreaterThan(
            10,
            strlen($badFile->heading()),
            'better-badfile-heading',
        );

        global $Cache;
        $Cache->delete_value($badFile->totalCacheKey());
        $initialTotal = $badFile->total();

        $this->tgroup = Helper::makeTGroupMusic(
            user: $this->user,
            name: 'phpunit better bad ' . randomString(6),
            artistName: [[ARTIST_MAIN], ['phpunit better ' . randomString(12)]],
            tagName: ['bop'],
            image: '',
        );
        $torrent = Helper::makeTorrentMusic(
            tgroup: $this->tgroup,
            user:   $this->user,
            title: 'phpunit better bad ' . randomString(10),
        );
        $torrent->addFlag(Enum\TorrentFlag::badFile, $this->user);

        $Cache->delete_value($badFile->totalCacheKey());
        $this->assertEquals(
            1,
            $badFile->total() - $initialTotal,
            'better-bad-badfile-total'
        );
        $torrent->addFlag(Enum\TorrentFlag::badFolder, $this->user);
        $badFolder = new Better\Bad(
            $this->user, Enum\BetterFilter::snatched, $torMan,
        )->setBadType(Enum\TorrentFlag::badFolder);
        $Cache->delete_value($badFolder->totalCacheKey());
        $this->assertEquals(
            0,
            $badFolder->total(),
            'better-bad-badfolder-snatched-total'
        );
        $this->assertNotEquals(
            $badFile->heading(),
            $badFolder->heading(),
            'better-badfolder-heading',
        );
    }

    public function testBetterChecksum(): void {
        $torMan = new Manager\Torrent();
        $checksum = new Better\Checksum($this->user, Enum\BetterFilter::any, $torMan);
        $this->assertEquals('torrent', $checksum->mode(), 'better-checksum-mode');
        $this->assertGreaterThan(
            10,
            strlen($checksum->heading()),
            'better-checksum-heading',
        );

        global $Cache;
        $Cache->delete_value($checksum->totalCacheKey());
        $initialTotal = $checksum->total();

        $this->tgroup = Helper::makeTGroupMusic(
            user: $this->user,
            name: 'phpunit better checksum ' . randomString(6),
            artistName: [[ARTIST_MAIN], ['phpunit better ' . randomString(12)]],
            tagName: ['bop'],
            image: '',
        );
        $torrent = Helper::makeTorrentMusic(
            tgroup: $this->tgroup,
            user:   $this->user,
            title: 'phpunit better checksum ' . randomString(10),
        );
        $torrent->setField('HasLogDB', '1')->setField('LogChecksum', '0')->modify();

        $Cache->delete_value($checksum->totalCacheKey());
        $this->assertEquals(
            1,
            $checksum->total() - $initialTotal,
            'better-bad-checksum-total'
        );

        $snatched = new Better\Checksum($this->user, Enum\BetterFilter::snatched, $torMan);
        $Cache->delete_value($snatched->totalCacheKey());
        $search = randomString(10);
        $snatched->setSearch($search);
        $this->assertEquals(
            $search,
            $snatched->search(),
            'better-bad-checksum-search',
        );
        $this->assertEquals(
            0,
            $snatched->total(),
            'better-bad-checksum-snatched->total',
        );

        $uploaded = new Better\Checksum($this->user, Enum\BetterFilter::uploaded, $torMan);
        $Cache->delete_value($uploaded->totalCacheKey());
        $this->assertEquals(
            1,
            $uploaded->total(),
            'better-bad-checksum-uploaded-total',
        );
    }

    public function testBetterSingleSeeded(): void {
        $torMan = new Manager\Torrent();
        $single = new Better\SingleSeeded($this->user, Enum\BetterFilter::uploaded, $torMan);
        $single->setUploader($this->user);
        $this->assertGreaterThan(
            10,
            strlen($single->heading()),
            'better-single-heading',
        );

        global $Cache;
        $Cache->delete_value($single->totalCacheKey());
        $initialTotal = $single->total();

        $this->tgroup = Helper::makeTGroupMusic(
            user: $this->user,
            name: 'phpunit better single seed ' . randomString(6),
            artistName: [[ARTIST_MAIN], ['phpunit better ' . randomString(12)]],
            tagName: ['bop'],
            image: '',
        );
        $torrent = Helper::makeTorrentMusic(
            tgroup: $this->tgroup,
            user:   $this->user,
            title:  'phpunit better single seed ' . randomString(10),
            format: 'FLAC',
        );
        Helper::addTorrentTraffic($torrent, seedTotal: 1);

        $Cache->delete_value($single->totalCacheKey());
        $this->assertEquals(
            1,
            $single->total(),
            'better-single-total',
        );
    }
}
