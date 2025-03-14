<?php

namespace Gazelle;

use PHPUnit\Framework\TestCase;
use Gazelle\Enum\DownloadStatus;
use Gazelle\Enum\TorrentFlag;
use Gazelle\Enum\UserTorrentSearch;

class TorrentTest extends TestCase {
    protected Torrent $torrent;
    protected User    $user;
    protected array   $userList;

    public function setUp(): void {
        $this->user    = \GazelleUnitTest\Helper::makeUser('torrent.' . randomString(10), 'rent', clearInbox: true);
        $this->torrent = \GazelleUnitTest\Helper::makeTorrentMusic(
            tgroup: \GazelleUnitTest\Helper::makeTGroupMusic(
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
        \GazelleUnitTest\Helper::removeTGroup($this->torrent->group(), $this->user);
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

    public function testContents(): void {
        $bencoder = new \OrpheusNET\BencodeTorrent\BencodeTorrent();
        $bencoder->decodeFile(__DIR__ . '/../fixture/valid_torrent.torrent');
        $info = $bencoder->getData();
        $this->assertIsArray($info, 'torrent-file-data-array');
        $torrentFiler = new File\Torrent();
        $this->assertTrue(
            $torrentFiler->put($bencoder->getEncode(), $this->torrent->id()),
            'torrent-file-put'
        );
        $this->assertTrue(
            $torrentFiler->exists($this->torrent->id()),
            'torrent-file-exists'
        );

        ['total_size' => $totalSize, 'files' => $fileList] = $bencoder->getFileList();
        $torMan = new Manager\Torrent();
        ['path' => $filename, 'size' => $size] = $fileList[0];
        $this->assertEquals(
            ".flac s2056192s 1 track1.flac ÷",
            $torMan->metaFilename($filename, $size),
            'torrent-file-meta-filename',
        );

        $dbFileList = [];
        foreach ($fileList as ['path' => $filename, 'size' => $size]) {
            $dbFileList[] = $torMan->metaFilename($filename, $size);
        }
        $this->torrent->setField('FileList', implode("\n", $dbFileList))
            ->modify();

        $this->assertEquals(
            ['flac' => 2],
            $this->torrent->fileListPrimaryMap(),
            'torrent-file-primary-map'
        );
        $this->assertEquals(
            2,
            $this->torrent->fileListPrimaryTotal(),
            'torrent-file-primary-total'
        );
        $this->assertEquals(
            7228,
            $this->torrent->fileListNonPrimarySize(),
            'torrent-file-non-primary-size'
        );
        $this->assertEquals(
            "1 track1.flac{{{2056192}}}|||2 track2.flac{{{6152192}}}|||Test Album.log{{{7228}}}",
            $this->torrent->fileListLegacyAPI(),
            'torrent-file-legacy-list'
        );

        $this->assertEquals(8215612, $totalSize, 'torrent-file-total-size');
        $this->assertCount(3, $fileList, 'torrent-file-list');
        $this->assertTrue(
            $torrentFiler->remove($this->torrent->id()),
            'torrent-file-remove'
        );
    }

    public function testRemovalPm(): void {
        $torrent = \GazelleUnitTest\Helper::makeTorrentMusic(
            tgroup: $this->torrent->group(),
            user:   $this->user,
            title:  randomString(10),
        );

        // a downloader
        $this->userList['downloader'] = \GazelleUnitTest\Helper::makeUser('torrent.' . randomString(10), 'rent', clearInbox: true);
        $status = new Download($torrent, new User\UserclassRateLimit($this->userList['downloader']), false);
        $this->assertEquals(DownloadStatus::ok, $status->status(), 'torrent-removal-download');

        // a snatcher
        $this->userList['snatcher'] = \GazelleUnitTest\Helper::makeUser('torrent.' . randomString(10), 'rent', clearInbox: true);
        \GazelleUnitTest\Helper::generateTorrentSnatch($torrent, $this->userList['snatcher']);

        // a seeder
        $this->userList['seeder'] = \GazelleUnitTest\Helper::makeUser('torrent.' . randomString(10), 'rent', clearInbox: true);
        \GazelleUnitTest\Helper::generateTorrentSeed($torrent, $this->userList['seeder']);

        $name = $torrent->fullName();
        $path = $torrent->path();
        $this->assertEquals(
            4,
            (new Manager\User())->sendRemovalPm(
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

    public function testRemoveAllLogs(): void {
        $this->assertEquals(
            0,
            $this->torrent->removeAllLogs(
                $this->user,
                new File\RipLog(),
                new File\RipLogHTML(),
            ),
            'torrent-remove-all-logs'
        );
    }

    public function testTorrentBBCode(): void {
        $torrentId = $this->torrent->id();
        $tgroupId  = $this->torrent->group()->id();

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
        $userTorrent = new Search\UserTorrent($this->user, UserTorrentSearch::uploaded);
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
}
