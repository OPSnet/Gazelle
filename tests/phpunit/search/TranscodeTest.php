<?php

namespace Gazelle;

use Gazelle\Enum\BetterEncoding;
use Gazelle\Enum\BetterFilter;
use PHPUnit\Framework\TestCase;
use GazelleUnitTest\Helper;

class TranscodeTest extends TestCase {
    protected array $torrentList;
    protected User $user;
    protected Search\Transcode $search;

    public function setUp(): void {
        $this->user = Helper::makeUser('torrent.' . randomString(10), 'bettertranscode');
        $this->user->requestContext()->setViewer($this->user);
        $tgroup = Helper::makeTGroupMusic(
            user: $this->user,
            name: 'phpunit torrent xcode ' . randomString(6),
            artistName: [[ARTIST_MAIN], ['phpunit torrent ' . randomString(12)]],
            tagName: ['bop'],
        );
        $remaster = randomString(10);
        $this->torrentList = [
            Helper::makeTorrentMusic(
                tgroup: $tgroup,
                user:  $this->user,
                title: $remaster,
                seed: true
            ),
            Helper::makeTorrentMusic(
                tgroup: $tgroup,
                user: $this->user,
                format: 'MP3',
                encoding: '320',
                title: $remaster
            )
        ];
        $this->search = new Search\Transcode($this->user)->setSearch($tgroup->name());
        new Manager\TGroup()->refreshBetter();
    }

    public function tearDown(): void {
        Helper::removeUser($this->user);
    }

    public function testSearchTranscode(): void {
        $this->assertCount(1, $this->search->page(10, 0), 'searchtranscode-basic');

        $this->search->setMode(BetterFilter::seeding);
        $this->assertCount(1, $this->search->page(10, 0), 'searchtranscode-seeding');

        $this->search->setMode(BetterFilter::uploaded);
        $this->assertCount(1, $this->search->page(10, 0), 'searchtranscode-uploaded');

        $this->search->setMode(BetterFilter::snatched);
        $this->assertCount(0, $this->search->page(10, 0), 'searchtranscode-snatched');

        $this->search->setMode(BetterFilter::any)->setEncoding(BetterEncoding::cbr320);
        $this->assertCount(0, $this->search->page(10, 0), 'searchtranscode-320');

        $this->search->setEncoding(BetterEncoding::v0);
        $this->assertCount(1, $this->search->page(10, 0), 'searchtranscode-v0');

        $this->search->setEncoding(BetterEncoding::all);
        $this->assertCount(0, $this->search->page(10, 0), 'searchtranscode-allenc');

        $this->search->setEncoding(BetterEncoding::any);
        $this->assertCount(1, $this->search->page(10, 0), 'searchtranscode-anyenc');
        $this->assertEquals(['all' => 1, 'total_320' => 0, 'total_v0' => 1], $this->search->total(), 'searchtranscode-total');
    }

    public function testSearchTranscodeSearch(): void {
        $this->search->setSearch('xcode');
        $this->assertEqualsCanonicalizing(
            ['all' => 1, 'want_320' =>  0, 'want_v0' => 1],
            $this->search->total(),
            'searchtranscode-search-total',
        );
        $this->assertCount(1, $this->search->page(10, 0), 'searchtranscode-search-page');
    }
}
