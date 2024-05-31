<?php

use PHPUnit\Framework\TestCase;
use Gazelle\Enum\DownloadStatus;
use Gazelle\Enum\TorrentFlag;

require_once(__DIR__ . '/../../lib/bootstrap.php');
require_once(__DIR__ . '/../helper.php');

class TorrentTest extends TestCase {
    protected \Gazelle\Torrent $torrent;
    protected \Gazelle\User    $user;
    protected array            $userList;

    public function setUp(): void {
        $this->user    = Helper::makeUser('torrent.' . randomString(10), 'rent', clearInbox: true);
        $this->torrent = Helper::makeTorrentMusic(
            tgroup: Helper::makeTGroupMusic(
                name:       'phpunit torrent ' . randomString(6),
                artistName: [[ARTIST_MAIN], ['phpunit torrent ' . randomString(12)]],
                tagName:    ['jazz'],
                user:       $this->user,
            ),
            user:  $this->user,
            title: randomString(10),
        );
    }

    public function tearDown(): void {
        Helper::removeTGroup($this->torrent->group(), $this->user);
        $this->user->remove();
        if (isset($this->userList)) {
            foreach ($this->userList as $user) {
                $user->remove();
            }
        }
    }

    public function testFlag(): void {
        $this->assertFalse($this->torrent->hasFlag(TorrentFlag::badFile), 'torrent-no-bad-file-flag');
        $this->assertEquals(1, $this->torrent->addFlag(TorrentFlag::badFile, $this->user), 'torrent-add-bad-file-flag');
        $this->assertTrue($this->torrent->hasFlag(TorrentFlag::badFile), 'torrent-has-bad-file-flag');

        $this->assertEquals(1, $this->torrent->removeFlag(TorrentFlag::badFile), 'torrent-remove-bad-file-flag');
        $this->assertEquals(0, $this->torrent->removeFlag(TorrentFlag::badFolder), 'torrent-remove-no-flag');
        $this->assertFalse($this->torrent->hasFlag(TorrentFlag::badFile), 'torrent-no-more--bad-file-flag');
    }

    public function testRemovalPm(): void {
        $torrent = Helper::makeTorrentMusic(
            tgroup: $this->torrent->group(),
            user:   $this->user,
            title:  randomString(10),
        );

        // a downloader
        $this->userList['downloader'] = Helper::makeUser('torrent.' . randomString(10), 'rent', clearInbox: true);
        $status = new Gazelle\Download($torrent, new \Gazelle\User\UserclassRateLimit($this->userList['downloader']), false);
        $this->assertEquals(DownloadStatus::ok, $status->status(), 'torrent-removal-download');

        // a snatcher
        $this->userList['snatcher'] = Helper::makeUser('torrent.' . randomString(10), 'rent', clearInbox: true);
        Helper::generateTorrentSnatch($torrent, $this->userList['snatcher']);

        // a seeder
        $this->userList['seeder'] = Helper::makeUser('torrent.' . randomString(10), 'rent', clearInbox: true);
        Helper::generateTorrentSeed($torrent, $this->userList['seeder']);

        $name = $torrent->fullName();
        $path = $torrent->path();
        $this->assertEquals(
            4,
            (new \Gazelle\Manager\User())->sendRemovalPm(
                $this->user,
                $torrent->id(),
                $name,
                $path,
                log: 'phpunit removal test',
                replacementId: 0,
                pmUploader: true
            ),
            'torrent-removal-pm'
        );

        // uploader
        $inbox = $this->user->inbox();
        $this->assertEquals(1, $inbox->messageTotal(), 'torrent-removal-uploader-message-count');
        $list = $inbox->messageList(new Gazelle\Manager\PM($this->user), 2, 0);
        $pm = $list[0];
        $this->assertStringStartsWith(
            "Uploaded torrent deleted: $name",
            $pm->subject(),
            'torrent-removal-pm-subject'
        );
        $postList = $pm->postList(1, 0);
        $this->assertStringStartsWith(
            "A torrent that you uploaded has been deleted.",
            $postList[0]['body'],
            'torrent-removal-uploader-pm-recv-body'
        );

        // downloader
        $inbox = $this->userList['downloader']->inbox();
        $this->assertEquals(1, $inbox->messageTotal(), 'torrent-removal-downloader-message-count');
        $list = $inbox->messageList(new Gazelle\Manager\PM($this->userList['downloader']), 2, 0);
        $pm = $list[0];
        $this->assertStringStartsWith(
            "Downloaded torrent deleted: $name",
            $pm->subject(),
            'torrent-removal-downloader-pm-subject'
        );
        $postList = $pm->postList(1, 0);
        $this->assertStringStartsWith(
            "A torrent that you have downloaded has been deleted.",
            $postList[0]['body'],
            'torrent-removal-downloader-pm-recv-body'
        );

        // snatcher
        $inbox = $this->userList['snatcher']->inbox();
        $this->assertEquals(1, $inbox->messageTotal(), 'torrent-removal-snatcher-message-count');
        $list = $inbox->messageList(new Gazelle\Manager\PM($this->userList['snatcher']), 2, 0);
        $pm = $list[0];
        $this->assertStringStartsWith(
            "Snatched torrent deleted: $name",
            $pm->subject(),
            'torrent-removal-snatcher-pm-subject'
        );
        $postList = $pm->postList(1, 0);
        $this->assertStringStartsWith(
            "A torrent that you have snatched has been deleted.",
            $postList[0]['body'],
            'torrent-removal-snatcher-pm-recv-body'
        );

        // seeder
        $inbox = $this->userList['seeder']->inbox();
        $this->assertEquals(1, $inbox->messageTotal(), 'torrent-removal-seeder-message-count');
        $list = $inbox->messageList(new Gazelle\Manager\PM($this->userList['seeder']), 2, 0);
        $pm = $list[0];
        $this->assertStringStartsWith(
            "Seeded torrent deleted: $name",
            $pm->subject(),
            'torrent-removal-seeder-pm-subject'
        );
        $postList = $pm->postList(1, 0);
        $this->assertStringStartsWith(
            "A torrent that you were seeding has been deleted.",
            $postList[0]['body'],
            'torrent-removal-pm-seeder-recv-body'
        );
    }

    public function testRemoveAllLogs(): void {
        $this->assertEquals(
            0,
            $this->torrent->removeAllLogs(
                $this->user,
                new Gazelle\File\RipLog(),
                new Gazelle\File\RipLogHTML(),
                new Gazelle\Log(),
            ),
            'torrent-remove-all-logs'
        );
    }

    public function testTorrentBBCode(): void {
        $torrentId = $this->torrent->id();
        $tgroupId  = $this->torrent->group()->id();

        $torrentRegexp = "^<a href=\"artist\.php\?id=\d+\" dir=\"ltr\">.*</a> – <a title=\".*?\" href=\"/torrents\.php\?id={$tgroupId}&torrentid={$torrentId}#torrent{$torrentId}\">.* \[\d+ .*?\]</a>";
        $this->assertMatchesRegularExpression("@{$torrentRegexp} .*$@",
            Text::full_format("[pl]{$torrentId}[/pl]"),
            'text-pl'
        );

        // FIXME: we generate torrent urls in two different ways
        $torrentRegexp = "^<a href=\"artist\.php\?id=\d+\" dir=\"ltr\">.*</a> – <a href=\"torrents\.php\?id={$tgroupId}&amp;torrentid={$torrentId}#torrent{$torrentId}\" dir=\"ltr\">.*</a> \[\d+ .*?\]";
        $this->assertMatchesRegularExpression("@{$torrentRegexp}@",
            Text::full_format(SITE_URL . "/{$this->torrent->location()}"),
            "text-torrent-url tg={$tgroupId} t={$torrentId}"
        );

        $tgroupRegexp = "<a href=\"artist\.php\?id=\d+\" dir=\"ltr\">.*?</a> – <a href=\"torrents.php\?id={$tgroupId}\" title=\".*?\" dir=\"ltr\">.*?</a> \[\d+ \S+\]";
        $this->assertMatchesRegularExpression("@{$tgroupRegexp}@",
            Text::full_format("[torrent]{$tgroupId}[/torrent]"),
            'text-tgroup-id'
        );
        $this->assertMatchesRegularExpression("@{$tgroupRegexp}@",
            Text::full_format(SITE_URL . "/{$this->torrent->group()->location()}"),
            'text-tgroup-url'
        );
    }
}
