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
        $db = Gazelle\DB::DB();
        foreach ($this->userList as $user) {
            $db->scalar("DELETE FROM ratelimit_torrent WHERE user_id = ?", $user->id());
            $user->remove();
        }
    }

    public function testBasic(): void {
        $uploader = new Gazelle\Download($this->torrent, new \Gazelle\User\UserclassRateLimit($this->userList['up']), false);
        $this->assertEquals(DownloadStatus::ok, $uploader->status(), 'download-uploader-ok');

        $ratelimit = new \Gazelle\User\UserclassRateLimit($this->userList['down']);
        $this->assertFalse(is_nan($ratelimit->userclassFactor()), 'download-ratelimit-userclass-factor');
        $this->assertTrue(is_nan($ratelimit->userFactor()), 'download-ratelimit-user-factor');
        $this->assertFalse($ratelimit->hasExceededFactor(), 'download-ratelimit-factor');
        $this->assertFalse($ratelimit->hasExceededTotal(), 'download-ratelimit-total');
        $downloader = new Gazelle\Download($this->torrent, $ratelimit, false);
        $this->assertEquals(DownloadStatus::ok, $downloader->status(), 'download-downloader-ok');
        $this->assertIsInt($ratelimit->register($this->torrent), 'download-register-ratelimit');

        $this->userList['down']->setField('can_leech', 0)->modify();
        $ratio = new Gazelle\Download($this->torrent, new \Gazelle\User\UserclassRateLimit($this->userList['down']), false);
        $this->assertEquals(DownloadStatus::ratio, $ratio->status(), 'download-downloader-ratio-watch');
    }

    public function testFreeleech(): void {
        $none = new Gazelle\Download($this->torrent, new \Gazelle\User\UserclassRateLimit($this->userList['down']), true);
        $this->assertEquals(DownloadStatus::too_big, $none->status(), 'download-downloader-no-tokens');

        $this->userList['down']->updateTokens(2);
        $some = new Gazelle\Download($this->torrent, new \Gazelle\User\UserclassRateLimit($this->userList['down']), true);
        $this->assertEquals(DownloadStatus::too_big, $some->status(), 'download-downloader-some-tokens');
        $this->assertFalse($this->torrent->isFreeleechPersonal(), 'download-downloader-not-yet-free');

        $this->userList['down']->updateTokens(4);
        $enough = new Gazelle\Download($this->torrent, new \Gazelle\User\UserclassRateLimit($this->userList['down']), true);
        $this->assertEquals(DownloadStatus::ok, $enough->status(), 'download-downloader-enough-tokens');

        $this->assertEquals(1, $this->userList['down']->tokenCount(), 'download-downloader-spent-tokens');
        $this->assertTrue($this->userList['down']->hasToken($this->torrent), 'download-downloader-is-free');
        $this->assertTrue(
            $this->torrent->flush()->setViewer($this->userList['down'])->isFreeleechPersonal(),
            'download-torrent-is-free-personal'
        );
    }

    public function testRedownload(): void {
        $user = $this->userList['down'];
        $user->updateTokens(10);
        $limiter = new \Gazelle\User\UserclassRateLimit($user);
        $initial = new Gazelle\Download($this->torrent, $limiter, true);

        // download with token
        $this->assertEquals(DownloadStatus::ok, $initial->status(), 'redown-initial');
        $this->assertTrue($user->hasToken($this->torrent), 'redown-user-has-token');

        // time goes by and the user downloads the torrent
        $db = Gazelle\DB::DB();
        $db->prepared_query("
            INSERT INTO xbt_snatched (uid, fid, IP, seedtime, tstamp)
            VALUES                   (?,   ?, '127.0.0.1', 1, unix_timestamp(now()))
            ", $user->id(), $this->torrent->id()
        );
        $this->assertEquals(1, $this->torrent->expireToken($user), 'redown-expire-token');
        $this->assertFalse($user->flush()->hasToken($this->torrent), 'redown-user-no-more-token');
        $this->assertFalse($this->torrent->isFreeleechPersonal(), 'redown-torrent-is-not-pfl');

        // download again without token
        $redownload = new Gazelle\Download($this->torrent, $limiter, false);
        $this->assertEquals(DownloadStatus::ok, $redownload->status(), 'redown-redownload');
        $this->assertFalse($this->torrent->isFreeleechPersonal(), 'redown-torrent-is-still-not-pfl');

        (new Gazelle\Stats\Users)->refresh();
        $this->assertEquals(2, $user->stats()->downloadTotal(), 'redown-user-download-total');
        $this->assertEquals(1, $user->stats()->downloadUnique(), 'redown-user-download-unique');
    }
}
