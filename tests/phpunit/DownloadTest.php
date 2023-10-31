<?php

use PHPUnit\Framework\TestCase;
use Gazelle\Enum\DownloadStatus;

require_once(__DIR__ . '/../../lib/bootstrap.php');
require_once(__DIR__ . '/../helper.php');


class DownloadTest extends TestCase {
    protected \Gazelle\Torrent $torrent;
    protected array $userList;

    public function setUp(): void {
        $this->userList = [
            'up'   => Helper::makeUser('upload.' . randomString(6), 'download'),
            'down' => Helper::makeUser('download.' . randomString(6), 'download'),
        ];
        $this->torrent = Helper::makeTorrentMusic(
            tgroup: Helper::makeTGroupMusic(
                name:       'phpunit download ' . randomString(6),
                artistName: [[ARTIST_MAIN], ['the ' . randomString(12) . ' Band']],
                tagName:    ['downtempo'],
                user:       $this->userList['up'],
            ),
            user: $this->userList['up'],
            size: (int)(BYTES_PER_FREELEECH_TOKEN * 2.5), // need three tokens to play
        );
    }

    public function tearDown(): void {
        Helper::removeTGroup($this->torrent->group(), $this->torrent->uploader());
        foreach ($this->userList as $user) {
            $user->remove();
        }
    }

    public function testBasic(): void {
        $uploader = new Gazelle\Download($this->userList['up'], $this->torrent, false);
        $this->assertEquals(DownloadStatus::ok, $uploader->status(), 'download-uploader-ok');

        $downloader = new Gazelle\Download($this->userList['down'], $this->torrent, false);
        $this->assertEquals(DownloadStatus::ok, $downloader->status(), 'download-downloader-ok');

        $this->userList['down']->setField('can_leech', 0)->modify();
        $ratio = new Gazelle\Download($this->userList['down'], $this->torrent, false);
        $this->assertEquals(DownloadStatus::ratio, $ratio->status(), 'download-downloader-ratio-watch');
    }

    public function testFreeleech(): void {
        $none = new Gazelle\Download($this->userList['down'], $this->torrent, true);
        $this->assertEquals(DownloadStatus::free, $none->status(), 'download-downloader-no-tokens');

        $this->userList['down']->updateTokens(2);
        $some = new Gazelle\Download($this->userList['down'], $this->torrent, true);
        $this->assertEquals(DownloadStatus::free, $some->status(), 'download-downloader-some-tokens');
        $this->assertFalse($this->torrent->isFreeleechPersonal(), 'download-downloader-not-yet-free');

        $this->userList['down']->updateTokens(4);
        $enough = new Gazelle\Download($this->userList['down'], $this->torrent, useToken: true);
        $this->assertEquals(DownloadStatus::ok, $enough->status(), 'download-downloader-enough-tokens');

        $this->assertEquals(1, $this->userList['down']->tokenCount(), 'download-downloader-spent-tokens');
        $this->assertTrue($this->userList['down']->hasToken($this->torrent), 'download-downloader-is-free');
        $this->assertTrue(
            $this->torrent->flush()->setViewer($this->userList['down'])->isFreeleechPersonal(),
            'download-torrent-is-free-personal'
        );
    }
}
