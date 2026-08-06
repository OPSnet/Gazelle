<?php

namespace Gazelle;

use PHPUnit\Framework\TestCase;
use GazelleUnitTest\Helper;
use Gazelle\Enum\DownloadStatus;
use Gazelle\Enum\TorrentFlag;
use Gazelle\Enum\UserTorrentSearch;
use OrpheusNET\BencodeTorrent\BencodeTorrent;
use OrpheusNET\Logchecker\Logchecker;

class TorrentTest extends TestCase {
    protected Torrent $torrent;
    protected User    $user;
    protected array   $userList;

    public function setUp(): void {
        $this->user = Helper::makeUser('torrent.' . randomString(10), 'rent', clearInbox: true);
        $this->user->requestContext()->setViewer($this->user);
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
        if (isset($this->userList)) {
            foreach ($this->userList as $user) {
                $user->remove();
            }
        }
        Helper::removeUser($this->user);
    }

    public function testFlag(): void {
        $this->assertFalse(
            $this->torrent->hasFlag(TorrentFlag::badFile),
            'torrent-no-bad-file-flag',
        );
        $this->assertEquals(
            1,
            $this->torrent->addFlag(TorrentFlag::badFile, $this->user),
            'torrent-add-bad-file-flag',
        );
        $this->assertTrue(
            $this->torrent->hasFlag(TorrentFlag::badFile),
            'torrent-has-bad-file-flag',
        );

        $this->assertEquals(
            1,
            $this->torrent->removeFlag(TorrentFlag::badFile, $this->user),
            'torrent-remove-bad-file-flag',
        );
        $this->assertEquals(
            0,
            $this->torrent->removeFlag(TorrentFlag::badFolder, $this->user),
            'torrent-remove-no-flag',
        );
        $this->assertFalse(
            $this->torrent->hasFlag(TorrentFlag::badFile),
            'torrent-no-more-bad-file-flag',
        );

        $log = new Manager\SiteLog()->tgroupLogList($this->torrent->group());
        $this->assertCount(3, $log, 'torrent-flag-log-count');
        // reverse chronological, remove then add
        $this->assertEquals(
            "\"Bad Files\" flag removed from torrent {$this->torrent->id}",
            $log[0]['note'],
            'torrent-flag-log-add',
        );
        $this->assertEquals(
            "\"Bad Files\" flag added to torrent {$this->torrent->id}",
            $log[1]['note'],
            'torrent-flag-log-add',
        );
    }

    public function testContents(): void {
        $torrent  = $this->torrent;
        $bencoder = new BencodeTorrent();
        $bencoder->decodeFile(__DIR__ . '/../fixture/valid_torrent.torrent');
        $info = $bencoder->getData();
        $this->assertIsArray($info, 'torrent-file-data-array');
        $torrentFile = new File\Torrent($torrent->id);
        $this->assertTrue(
            $torrentFile->put($bencoder->getEncode()),
            'torrent-file-put'
        );
        $this->assertTrue(
            $torrentFile->exists(),
            'torrent-file-exists'
        );
        $bencoder->setSource('ZZZ');
        $this->assertTrue(
            new Manager\Torrent()->setSourceFlag($bencoder),
            'torrent-set-source-flag',
        );

        [
            'files'      => $fileList,
            'total_size' => $totalSize,
        ] = $bencoder->getFileList();
        $this->assertCount(3, $fileList, 'torrent-file-list');
        $this->assertEquals(8215612, $totalSize, 'torrent-file-total-size');
        $this->assertEquals(
            3,
            $torrent->regenerateFilelist(new BencodeTorrent()),
            'torrent-regenerate-file-list',
        );
        $this->assertEquals(
            "torrents.php?action=download&id={$torrent->id}&torrent_pass={$this->user->announceKey()}",
             $torrent->downloadLocation($this->user),
             'torrent-download-location',
         );

        $torMan = new Manager\Torrent();
        ['path' => $filename, 'size' => $size] = $fileList[0];
        $this->assertEquals(
            ".flac s2056192s 1 track1.flac ÷",
            $torMan->metaFilename($filename, $size),
            'torrent-file-meta-filename',
        );
        $tgroup = $torrent->group();
        $this->assertEquals(
            implode(
                '.',
                [
                    array_first($tgroup->artistRole()->roleList()['main'])['name'],
                    $tgroup->year(),
                    $tgroup->name(),
                    "(WEB-FLAC-Lossless)-{$torrent->id}.txt",
                ]
            ),
            $torrent->torrentFilename(asText: true, maxLength: 100),
            'torrent-torrent-filename',
        );
        $announceUrl = randomString(33);
        $this->assertStringStartsWith(
            "d8:announce33:$announceUrl",
            $torrent->torrentBody($announceUrl),
            'torrent-torrent-body',
        );

        $dbFileList = [];
        foreach ($fileList as ['path' => $filename, 'size' => $size]) {
            $dbFileList[] = $torMan->metaFilename($filename, $size);
        }
        $torrent->setField('FileList', implode("\n", $dbFileList))
            ->modify();

        $this->assertEquals(
            ['flac' => 2],
            $torrent->fileListPrimaryMap(),
            'torrent-file-primary-map'
        );
        $this->assertEquals(
            2,
            $torrent->fileListPrimaryTotal(),
            'torrent-file-primary-total'
        );
        $this->assertEquals(
            7228,
            $torrent->fileListNonPrimarySize(),
            'torrent-file-non-primary-size'
        );
        $this->assertEquals(
            "1 track1.flac{{{2056192}}}|||2 track2.flac{{{6152192}}}|||Test Album.log{{{7228}}}",
            $torrent->fileListLegacyAPI(),
            'torrent-file-legacy-list'
        );

        $torrent->unlockUpload();
        $this->assertFalse(
            $torrent->hasUploadLock(),
            'torrent-no-upload-lock'
        );
        $torrent->lockUpload();
        $this->assertTrue(
            $torrent->hasUploadLock(),
            'torrent-has-upload-lock'
        );
        $torrent->unlockUpload();
        $this->assertFalse(
            $torrent->hasUploadLock(),
            'torrent-upload-unlocked'
        );

        $this->assertEquals(
            "<a href=\"torrents.php?id={$tgroup->id}&amp;torrentid={$torrent->id}#torrent{$torrent->id}\">[WEB / FLAC / Lossless]</a>",
            $torrent->shortLabelLink(),
            'torrent-short-label-link',
        );

        $this->assertEquals(
            1,
            $torrentFile->remove(),
            'torrent-file-remove'
        );
    }

    public function testJsonUserTorrents(): void {
        $torrent = Helper::makeTorrentMusic(
            tgroup: $this->torrent->group(),
            user:   $this->user,
            title:  randomString(10),
        );

        // downloader
        $this->assertEquals(
            new Download($torrent, new User\UserclassRateLimit($this->user), false)->status(),
            Enum\DownloadStatus::ok,
            'torrent-user-json-download-ok'
        );

        $download = new Json\UserTorrents(
            $this->user,
            $this->user,
            Enum\UserTorrentSearch::from('downloaded'),
            2,
            0
        )->payload();
        $this->assertEquals(1, $download['total'], 'torrent-user-json-download-total');
        $this->assertEquals(
            $torrent->groupId(),
            $download['downloaded'][0]['groupId'],
            'torrent-user-json-download-id',
        );

        // uploader
        $upload = new Json\UserTorrents(
            $this->user,
            $this->user,
            Enum\UserTorrentSearch::from('uploaded'),
            2,
            0
        )->payload();
        $this->assertEquals(2, $upload['total'], 'torrent-user-json-upload-total');
        $this->assertEquals(
            $torrent->groupId(),
            $upload['uploaded'][0]['groupId'],
            'torrent-user-json-upload-id',
        );

        // uploader-unseeded
        $upUnseed = new Json\UserTorrents(
            $this->user,
            $this->user,
            Enum\UserTorrentSearch::from('uploaded-unseeded'),
            2,
            0
        )->payload();
        $this->assertEquals(2, $upUnseed['total'], 'torrent-user-json-up-unseed-total');
        $this->assertEquals(
            $torrent->groupId(),
            $upUnseed['uploaded-unseeded'][0]['groupId'],
            'torrent-user-json-up-uneed-id',
        );

        // leecher
        $leech = new Json\UserTorrents(
            $this->user,
            $this->user,
            Enum\UserTorrentSearch::from('leeching'),
            2,
            0
        )->payload();
        $this->assertEquals(0, $leech['total'], 'torrent-user-json-leech-total');

        // snatcher
        $snatch = new Json\UserTorrents(
            $this->user,
            $this->user,
            Enum\UserTorrentSearch::from('snatched'),
            2,
            0
        )->payload();
        $this->assertEquals(0, $snatch['total'], 'torrent-user-json-snatch-total');

        // snatched-unseeded
        $snatchUnseed = new Json\UserTorrents(
            $this->user,
            $this->user,
            Enum\UserTorrentSearch::from('snatched-unseeded'),
            2,
            0
        )->payload();
        $this->assertEquals(0, $snatchUnseed['total'], 'torrent-user-json-snatch-unseed-total');

        // seeder
        $seed = new Json\UserTorrents(
            $this->user,
            $this->user,
            Enum\UserTorrentSearch::from('seeding'),
            2,
            0
        )->payload();
        $this->assertEquals(0, $seed['total'], 'torrent-user-json-seed-total');
    }

    public function testRemovalPm(): void {
        $torrent = Helper::makeTorrentMusic(
            tgroup: $this->torrent->group(),
            user:   $this->user,
            title:  randomString(10),
        );

        // a downloader
        $this->userList['downloader'] = Helper::makeUser('torrent.' . randomString(10), 'rent', clearInbox: true);
        $status = new Download($torrent, new User\UserclassRateLimit($this->userList['downloader']), false);
        $this->assertEquals(DownloadStatus::ok, $status->status(), 'torrent-removal-download');

        // a snatcher
        $this->userList['snatcher'] = Helper::makeUser('torrent.' . randomString(10), 'rent', clearInbox: true);
        Helper::generateTorrentSnatch($torrent, $this->userList['snatcher']);

        // check recent snatchlist
        $this->assertEquals(
            [$torrent->groupId()],
            $this->userList['snatcher']->snatch()->recentSnatchList(),
            'utest-recent-snatch'
        );

        // a seeder
        $this->userList['seeder'] = Helper::makeUser('torrent.' . randomString(10), 'rent', clearInbox: true);
        $useragent = 'uahist-' . randomString(10);
        Helper::generateTorrentSeed(
            $torrent,
            $this->userList['seeder'],
            ipAddr: '127.1.1.1',
            useragent: $useragent
        );

        // This is as good a place as any to test this
        new Stats\Users()->refreshUseragentTracker();
        $this->assertEquals(
            [[
                "useragent" => $useragent,
                "total"     => 1,
            ]],
            $this->userList['seeder']->stats()->historyUseragentTracker(),
            'user-status-history-useragent-tracker',
        );

        $name = $torrent->fullName();
        $path = $torrent->path();
        $this->assertEquals(
            4,
            new Manager\User()->sendRemovalPm(
                $this->user,
                $torrent->id,
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
        $list = $inbox->messageList(new Manager\PM($this->user), 2, 0);
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
        $list = $inbox->messageList(new Manager\PM($this->userList['downloader']), 2, 0);
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
        $list = $inbox->messageList(new Manager\PM($this->userList['snatcher']), 2, 0);
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
        $list = $inbox->messageList(new Manager\PM($this->userList['seeder']), 2, 0);
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

    public function testFileList(): void {
        // create a list of files larger than 1 MiB (30 * 34 - 1 > 1024 ** 2)
        $count = 34;
        $fileList = implode(
            "\n",
            array_fill(0, $count, ".flac s1234s ABCDEFGH.flac " . FILELIST_DELIM)
        );
        $this->torrent->setField('FileList', $fileList)->modify();
        $list = $this->torrent->fileList();
        $this->assertCount($count, $list, 'torrent-large-filelist');
        // if things do not line up then there is an off-by-one somewhere
        for ($n = 0; $n < $count; $n++) {
            $this->assertEquals('ABCDEFGH.flac', $list[$n]['name'], "torrent-filelist-name-$n");
            $this->assertEquals('.flac',         $list[$n]['ext'],  "torrent-filelist-ext-$n");
            $this->assertEquals(1234,            $list[$n]['size'], "torrent-filelist-size-$n");
        }
    }

    public function testTorrentBBCode(): void {
        $torrentId = $this->torrent->id;
        $tgroupId  = $this->torrent->group()->id;

        $torrentRegexp = "^<a href=\"artist\.php\?id=\d+\" dir=\"ltr\">.*</a> – <a title=\".*?\" href=\"/torrents\.php\?id={$tgroupId}&torrentid={$torrentId}#torrent{$torrentId}\">.* \[\d+ .*?\]</a>";
        $this->assertMatchesRegularExpression("@{$torrentRegexp} .*$@",
            \Text::full_format("[pl]{$torrentId}[/pl]"),
            'text-pl'
        );

        // FIXME: we generate torrent urls in two different ways
        $torrentRegexp = "^<a href=\"artist\.php\?id=\d+\" dir=\"ltr\">.*</a> – <a href=\"torrents\.php\?id={$tgroupId}&amp;torrentid={$torrentId}#torrent{$torrentId}\" dir=\"ltr\">.*</a> \[\d+ .*?\]";
        $this->assertMatchesRegularExpression("@{$torrentRegexp}@",
            \Text::full_format(SITE_URL . "/{$this->torrent->location()}"),
            "text-torrent-url tg={$tgroupId} t={$torrentId}"
        );

        $tgroupRegexp = "<a href=\"artist\.php\?id=\d+\" dir=\"ltr\">.*?</a> – <a href=\"torrents.php\?id={$tgroupId}\" title=\".*?\" dir=\"ltr\">.*?</a> \[\d+ \S+\]";
        $this->assertMatchesRegularExpression("@{$tgroupRegexp}@",
            \Text::full_format("[torrent]{$tgroupId}[/torrent]"),
            'text-tgroup-id'
        );
        $this->assertMatchesRegularExpression("@{$tgroupRegexp}@",
            \Text::full_format(SITE_URL . "/{$this->torrent->group()->location()}"),
            'text-tgroup-url'
        );
    }

    public function testTorrentCollector(): void {
        $title = 'phpunit-zip';
        $collector = new Collector\TList($this->user, new Manager\Torrent(), $title, 0);
        $userTorrent = new Search\UserTorrent($this->user, $this->user, UserTorrentSearch::uploaded, 10, 0);
        $this->assertCount(1, $userTorrent->idList(), 'torrent-search-upload');
        $collector->setList($userTorrent->idList());
        $this->assertTrue($collector->prepare([]), 'collect-tlist-prepare');

        $zip = Util\Zip::make($title);
        $this->assertInstanceOf(\ZipStream\ZipStream::class, $zip, 'collect-zipper');
        $this->assertEquals(1, $collector->fillZip($zip), 'collect-tlist-fill');
        $this->assertStringContainsString(
            "Torrent groups scanned: 1\n",
            $collector->summary(),
            "collector-tlist-summary",
        );
    }

    public function testTorrentReseed(): void {
        $this->assertEquals(
            1,
            $this->torrent->issueReseedRequest($this->user),
            'torrent-issue-reseed-request',
        );
        $thread = array_first(
            $this->user->inbox()
                ->messageList(new Manager\PM($this->user), 1, 0)
        );
        $tgroup = $this->torrent->group();
        $artist = array_first($tgroup->artistRole()->roleList()['main'])['name'];
        $this->assertEquals(
            "Re-seed request for torrent $artist – {$tgroup->name()} [{$tgroup->year()} Album]",
            $thread->subject(),
            'torrent-reseed-subject',
        );
        $message = array_first($thread->postList(1, 0));
        $this->assertTrue(
            Helper::recentDate($message['sent_date']),
            'torrent-reseed-sent-date',
        );
        $this->assertStringContainsString(
            "[user]{$this->user->username()}[/user]",
            $message['body'],
            'torrent-reseed-message-sender'
        );
        $this->assertStringContainsString(
            "[url=torrents.php?id={$tgroup->id}&torrentid={$this->torrent->id}#{$this->torrent->id}]",
            $message['body'],
            'torrent-reseed-message-location'
        );
    }
}
