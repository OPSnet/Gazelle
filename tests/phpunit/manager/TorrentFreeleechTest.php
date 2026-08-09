<?php

namespace Gazelle;

use PHPUnit\Framework\TestCase;
use GazelleUnitTest\Helper;
use Gazelle\Enum\LeechType;
use Gazelle\Enum\LeechReason;

class TorrentFreeleechTest extends TestCase {
    protected array $torrentList;

    public function setUp(): void {
        $user = Helper::makeUser('torman.' . randomString(10), 'torrent.manager');
        $user->requestContext()->setViewer($user);
        $tgroup = Helper::makeTGroupMusic(
            name:       'phpunit torman ' . randomString(6),
            artistName: [[ARTIST_MAIN], ['DJ Torman ' . randomString(12)]],
            tagName:    ['hip.hop'],
            user:       $user,
        );
        $this->torrentList = array_map(fn ($info) =>
            Helper::makeTorrentMusic(
                tgroup:   $tgroup,
                encoding: $info['encoding'],
                format:   $info['format'],
                media:    $info['media'],
                size:     $info['size'],
                title:    $info['title'],
                user:     $user,
            ), [
                ['encoding' => '24bit Lossless', 'format' => 'FLAC', 'media' => 'WEB',      'size' => 10_000_000, 'title' => 'Regular Edition'],
                ['encoding' => 'Lossless',       'format' => 'FLAC', 'media' => 'Cassette', 'size' => 15_000_000, 'title' => 'Limited Edition'],
                ['encoding' => 'Lossless',       'format' => 'FLAC', 'media' => 'CD',       'size' => 20_000_000, 'title' => 'Deluxe Edition'],
                ['encoding' => 'Lossless',       'format' => 'FLAC', 'media' => 'WEB',      'size' => 25_000_000, 'title' => 'Ultra Edition'],
                ['encoding' => '320',            'format' => 'MP3',  'media' => 'WEB',      'size' =>  2_000_000, 'title' => 'Ultra Edition'],
            ]
        );
        // make the CD torrent 100%
        $this->torrentList[2]
            ->setField('HasCue',      '1')
            ->setField('HasLog',      '1')
            ->setField('HasLogDB',    '1')
            ->setField('LogChecksum', '1')
            ->setField('LogScore',    100)
            ->modify();
    }

    public function tearDown(): void {
        Helper::removeUser($this->torrentList[0]->uploader());
    }

    public function testTorrentFreeleechThreshold(): void {
        $this->assertSame(
            4,
            $this->torrentList[0]->group()->setFreeleech(
                leechType: LeechType::Free,
                reason:    LeechReason::StaffPick,
                threshold: 22_000_000,
                user:      $this->torrentList[0]->uploader(),
            ),
            'fl-free-staffpick-22M'
        );

        $this->assertSame(LeechType::Free,    $this->torrentList[0]->flush()->leechType(), 'torman-0-is-free');
        $this->assertSame(LeechType::Free,    $this->torrentList[1]->flush()->leechType(), 'torman-1-is-free');
        $this->assertSame(LeechType::Free,    $this->torrentList[2]->flush()->leechType(), 'torman-2-is-free');
        $this->assertSame(LeechType::Neutral, $this->torrentList[3]->flush()->leechType(), 'torman-3-is-neutral');
        $this->assertSame(LeechType::Normal,  $this->torrentList[4]->flush()->leechType(), 'torman-4-is-normal');

        // here is as good a place as any to test these methods
        foreach (range(0, 2) as $n) {
            $this->assertTrue(
                $this->torrentList[0]->isPerfecterFlac(),
                "torrent-$n-is-web-perfecter",
            );
        }
        $this->assertTrue(
            $this->torrentList[3]->isNeutralLeech(),
            'torrent-is-nl',
        );
        $this->assertTrue(
            $this->torrentList[0]->unseeded(),
            'torrent-is-unseeded',
        );

        $this->assertSame(
            4,
            $this->torrentList[0]->group()->setFreeleech(
                leechType: LeechType::Normal,
                reason:    LeechReason::Normal,
                user:      $this->torrentList[0]->uploader(),
            ),
            'fl-normal'
        );

        $this->assertSame(LeechType::Normal, $this->torrentList[0]->flush()->leechType(), 'torman-0-is-now-normal');
        $this->assertSame(LeechType::Normal, $this->torrentList[1]->flush()->leechType(), 'torman-1-is-now-normal');
        $this->assertSame(LeechType::Normal, $this->torrentList[2]->flush()->leechType(), 'torman-2-is-now-normal');
        $this->assertSame(LeechType::Normal, $this->torrentList[3]->flush()->leechType(), 'torman-3-is-now-normal');
        $this->assertSame(LeechType::Normal, $this->torrentList[4]->flush()->leechType(), 'torman-4-is-now-normal');
    }

    public function testTorrentFreeleechAllFree(): void {
        $this->assertSame(
            5,
            $this->torrentList[0]->group()->setFreeleech(
                all:       true,
                leechType: LeechType::Free,
                reason:    LeechReason::StaffPick,
                user:      $this->torrentList[0]->uploader(),
            ),
            'fl-free-all'
        );

        $this->assertSame(LeechType::Free, $this->torrentList[3]->flush()->leechType(), 'torman-3-is-free');
        $this->assertSame(LeechType::Free, $this->torrentList[4]->flush()->leechType(), 'torman-4-is-free');
    }
}
