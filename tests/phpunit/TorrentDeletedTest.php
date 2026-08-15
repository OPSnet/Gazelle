<?php

namespace Gazelle;

use PHPUnit\Framework\TestCase;
use GazelleUnitTest\Helper;

class TorrentDeletedTest extends TestCase {
    protected Torrent $torrent;
    protected User    $user;

    public function setUp(): void {
        $this->user = Helper::makeUser('torrent.' . randomString(10), 'rent', clearInbox: true);
        $this->torrent = Helper::makeTorrentMusic(
            tgroup: Helper::makeTGroupMusic(
                name:       'phpunit torrent ' . randomString(6),
                artistName: [[ARTIST_MAIN], ['phpunit torrent ' . randomString(12)]],
                tagName:    ['pop'],
                user:       $this->user,
            ),
            user:   $this->user,
            title:  randomString(10),
            media:  'CD',
            hasCue: true,
            hasLog: true,
        );
        $this->torrent
            ->setField('LogScore', 100)
            ->setField('HasLogDB', '1')
            ->modify();
    }

    public function tearDown(): void {
        Helper::removeUser($this->user);
    }

    public function testTorrentDeletedBasic(): void {
        $torrentId = $this->torrent->id;
        $edition   = $this->torrent->edition();
        $fullName  = $this->torrent->fullName();
        $groupLink = $this->torrent->groupLink();
        $tgroup    = $this->torrent->group();

        $this->torrent->removeTorrent(null, 'phpunit', removePoints: false);
        $deleted = new Manager\Torrent()->findDeletedById($torrentId);

        $this->assertTrue($deleted->isDeleted(), 'torrent-is-deleted');
        $this->assertTrue($deleted->hasTGroup(), 'torrent-has-tgroup');
        $this->assertSame($torrentId, $deleted->id, 'torrent-deleted-id');
        $this->assertSame(
            "log.php?search=Torrent+{$deleted->id}",
            $deleted->location(),
            'torrent-deleted-location',
        );
        $this->assertSame(
            $edition,
            $deleted->edition(),
            'torrent-deleted-edition',
        );
        $this->assertSame(
            $fullName,
            $deleted->fullName(),
            'torrent-deleted-fullname',
        );
        $this->assertSame(
            $groupLink,
            $deleted->groupLink(),
            'torrent-deleted-groupLink',
        );
        $this->assertEqualsCanonicalizing(
            ["catalogue_number", "media", "record_label", "title", "year"],
            array_keys(array_first($tgroup->deletedMasteringList())),
            'torrent-deleted-mastering-list',
        );
        $this->assertStringContainsString(
            '<i>deleted</i>',
            Manager\Torrent::renderPL($deleted->id, []),
            'torrent-deleted-render-pl',
        );
    }

    public function testTorrentDeletedTGroup(): void {
        $torrentId = $this->torrent->id;
        $tgroup    = $this->torrent->group();
        $tgroupId  = $tgroup->id;
        $this->torrent->removeTorrent(null, 'phpunit', removePoints: false);
        $tgroup->removeTGroup();
        $tgroup->remove();
        $deleted = new Manager\Torrent()->findDeletedById($torrentId);

        $this->assertFalse($deleted->hasTGroup(), 'torrent-has-no-tgroup');
        $this->assertSame(
            "",
            $deleted->edition(),
            'torrent-tg-deleted-edition',
        );
        $this->assertSame(
            "Torrent {$torrentId} of deleted group #{$tgroupId}",
            $deleted->fullName(),
            'torrent-tg-deleted-fullname',
        );
        $this->assertSame(
            "Deleted group {$tgroupId}",
            $deleted->groupLink(),
            'torrent-tg-deleted-groupLink',
        );
    }
}
