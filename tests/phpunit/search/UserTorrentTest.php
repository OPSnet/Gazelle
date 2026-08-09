<?php

namespace Gazelle;

use PHPUnit\Framework\TestCase;
use GazelleUnitTest\Helper;
use Gazelle\Enum\UserTorrentSearch;

class UserTorrentTest extends TestCase {
    protected array $torrentList;
    protected User $user;
    protected User $viewer;

    public function setUp(): void {
        $this->user = Helper::makeUser('torrent.' . randomString(10), 'search');
        $this->user->requestContext()->setViewer($this->user);
        $this->viewer = Helper::makeUser('torrent.' . randomString(10), 'search');
        $this->torrentList = [
            Helper::makeTorrentMusic(
                tgroup: Helper::makeTGroupMusic(
                    name:       'phpunit torrent ' . randomString(6),
                    artistName: [[ARTIST_MAIN], ['phpunit torrent ' . randomString(12)]],
                    tagName:    ['pop'],
                    user:       $this->user,
                ),
                user:  $this->user,
                title: randomString(10),
            ),
            Helper::makeTorrentMusic(
                tgroup: Helper::makeTGroupMusic(
                    name:       'phpunit torrent ' . randomString(6),
                    artistName: [[ARTIST_MAIN], ['phpunit torrent ' . randomString(12)]],
                    tagName:    ['bop'],
                    user:       $this->user,
                ),
                user:  $this->user,
                title: randomString(10),
            )
        ];
    }

    public function tearDown(): void {
        Helper::removeUser($this->user);
        Helper::removeUser($this->viewer);
    }

    public function searchSelf(UserTorrentSearch $type): Search\UserTorrent {
        return new Search\UserTorrent(
            $this->user, $this->user, $type, 10, 0,
        );
    }

    public function searchOther(UserTorrentSearch $type): Search\UserTorrent {
        return new Search\UserTorrent(
            $this->user, $this->viewer, $type, 10, 0,
        );
    }

    public function testDownloaded(): void {
        $this->user->setField('Paranoia', serialize(['downloaded']))->modify();
        $torrent  = $this->torrentList[1];
        $download = new Download(
            $torrent,
            new User\UserclassRateLimit($this->user),
            false,
        );
        $this->assertSame(
            Enum\DownloadStatus::ok,
            $download->status(),
            'search-utor-download-ok',
        );
        $self = $this->searchSelf(UserTorrentSearch::downloaded);
        $this->assertSame(
            1,
            $self->total(),
            'search-utor-total-downloaded',
        );
        $this->assertSame(
            $torrent->id,
            array_first($self->torrentList())->id,
            'search-utor-list-downloaded'
        );
        $this->assertSame(
            0,
            $this->searchOther(UserTorrentSearch::downloaded)->total(),
            'search-utor-paranoia-downloaded',
        );
        $this->user->setField('Paranoia', serialize([]))->modify();
        $this->assertSame(
            1,
            $this->searchOther(UserTorrentSearch::downloaded)->total(),
            'search-utor-paranoia-ok-downloaded',
        );
    }

    public function testLeeching(): void {
        $this->user->setField('Paranoia', serialize(['leeching']))->modify();
        Helper::generateTorrentLeech($this->torrentList[1], $this->user);
        $self = $this->searchSelf(UserTorrentSearch::leeching);
        $this->assertSame(
            1,
            $self->total(),
            'search-utor-total-leeching',
        );
        $this->assertSame(
            [$this->torrentList[1]->id],
            $self->idList(),
            'search-utor-list-leeching'
        );
        $this->assertSame(
            0,
            $this->searchOther(UserTorrentSearch::leeching)->total(),
            'search-utor-paranoia-leeching',
        );
        $this->user->setField('Paranoia', serialize(['leeching+']))->modify();
        $this->assertSame(
            0,
            $this->searchOther(UserTorrentSearch::leeching)->total(),
            'search-utor-paranoia+-leeching',
        );
    }

    public function testSeeding(): void {
        $this->user->setField('Paranoia', serialize(['seeding']))->modify();
        Helper::generateTorrentSeed($this->torrentList[1], $this->user);
        $self = $this->searchSelf(UserTorrentSearch::seeding);
        $this->assertSame(
            1,
            $self->total(),
            'search-utor-total-seeding',
        );
        $this->assertSame(
            [$this->torrentList[1]->id],
            $self->idList(),
            'search-utor-list-seeding'
        );
        $this->assertSame(
            0,
            $this->searchOther(UserTorrentSearch::seeding)->total(),
            'search-utor-paranoia-seeding',
        );
        $this->user->setField('Paranoia', serialize(['seeding+']))->modify();
        $this->assertSame(
            0,
            $this->searchOther(UserTorrentSearch::seeding)->total(),
            'search-utor-paranoia+-seeding',
        );
    }

    public function testSnatched(): void {
        $this->user->setField('Paranoia', serialize(['snatched']))->modify();
        Helper::generateTorrentSnatch($this->torrentList[0], $this->user);
        $self = $this->searchSelf(UserTorrentSearch::snatched);
        $this->assertSame(
            1,
            $self->total(),
            'search-utor-total-snatch',
        );
        $this->assertSame(
            [$this->torrentList[0]->id],
            $self->idList(),
            'search-utor-list-snatched',
        );
        $this->assertSame(
            0,
            $this->searchOther(UserTorrentSearch::snatched)->total(),
            'search-utor-paranoia-snatched',
        );
        $this->user->setField('Paranoia', serialize(['snatched+']))->modify();
        $this->assertSame(
            0,
            $this->searchOther(UserTorrentSearch::snatched)->total(),
            'search-utor-paranoia+-snatched',
        );
    }

    public function testSnatchedUnseeded(): void {
        $this->user->setField('Paranoia', serialize(['snatched']))->modify();
        Helper::generateTorrentSnatch($this->torrentList[0], $this->user);
        $self = $this->searchSelf(UserTorrentSearch::snatchedUnseeded);
        $this->assertSame(
            1,
            $self->total(),
            'search-utor-total-snatch-unseeded',
        );
        $this->assertSame(
            [$this->torrentList[0]->id],
            $self->idList(),
            'search-utor-list-snatched-unseeded',
        );
        $this->assertSame(
            0,
            $this->searchOther(UserTorrentSearch::snatchedUnseeded)->total(),
            'search-utor-paranoia-snatched-unseeded',
        );
        $this->user->setField('Paranoia', serialize(['snatched+']))->modify();
        $this->assertSame(
            0,
            $this->searchOther(UserTorrentSearch::snatchedUnseeded)->total(),
            'search-utor-paranoia+-snatched-unseeded',
        );
    }

    public function testUploaded(): void {
        $this->user->setField('Paranoia', serialize(['uploads']))->modify();
        $self = $this->searchSelf(UserTorrentSearch::uploaded);
        $this->assertSame(
            2,
            $self->total(),
            'search-utor-total-uploaded',
        );
        $this->assertEqualsCanonicalizing(
            [$this->torrentList[0]->id, $this->torrentList[1]->id],
            $self->idList(),
            'search-utor-list-uploaded',
        );
        $this->assertSame(
            0,
            $this->searchOther(UserTorrentSearch::uploaded)->total(),
            'search-utor-paranoia-uploaded',
        );
        $this->user->setField('Paranoia', serialize(['uploads+']))->modify();
        $this->assertSame(
            0,
            $this->searchOther(UserTorrentSearch::uploaded)->total(),
            'search-utor-paranoia+uploaded',
        );
    }

    public function testUploadedUnseeded(): void {
        $this->user->setField('Paranoia', serialize(['uploads']))->modify();
        $self = $this->searchSelf(UserTorrentSearch::uploadedUnseeded);
        $this->assertSame(
            2,
            $self->total(),
            'search-utor-total-uploaded-unseeded',
        );
        $this->assertEqualsCanonicalizing(
            [$this->torrentList[0]->id, $this->torrentList[1]->id],
            $self->idList(),
            'search-utor-list-uploaded-unseeded',
        );
        $this->assertSame(
            0,
            $this->searchOther(UserTorrentSearch::uploadedUnseeded)->total(),
            'search-utor-paranoia-uploaded-unseeded',
        );
        $this->user->setField('Paranoia', serialize(['uploads+']))->modify();
        $this->assertSame(
            0,
            $this->searchOther(UserTorrentSearch::uploaded)->total(),
            'search-utor-paranoia+uploaded-unseeded',
        );
    }
}
