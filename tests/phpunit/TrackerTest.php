<?php

use PHPUnit\Framework\TestCase;
use Gazelle\Enum\DownloadStatus;
use Gazelle\Enum\LeechType;

require_once(__DIR__ . '/../../lib/bootstrap.php');
require_once(__DIR__ . '/../helper.php');

class TrackerTest extends TestCase {
    protected \Gazelle\Torrent $torrent;
    protected \Gazelle\User    $user;

    public function tearDown(): void {
        if (isset($this->torrent)) {
            Helper::removeTGroup($this->torrent->group(), $this->user);
        }
        if (isset($this->user)) {
            $this->user->remove();
        }
    }

    /**
     * @group no-ci
     */
    public function testTrackerStats(): void {
        $tracker = new \Gazelle\Tracker;
        $this->assertEquals([0, 0], $tracker->global_peer_count(), 'tracker-global-peer-count');
    }

    /**
     * @group no-ci
     */
    public function testTrackerToken(): void {
        $this->user = Helper::makeUser('trkfl.' . randomString(10), 'tracker');
        $this->torrent = Helper::makeTorrentMusic(
            Helper::makeTGroupMusic(
                name:       'tracker ' . randomString(10),
                artistName: [[ARTIST_MAIN], ['Tracker Girl ' . randomString(12)]],
                tagName:    ['trap'],
                user:       $this->user,
            ),
            user:  $this->user,
            title: 'tracker ' . randomString(10),
        );
        $tracker = new \Gazelle\Tracker;
        $this->assertTrue($tracker->addToken($this->torrent, $this->user), 'tracker-add-token');
        $this->assertTrue($tracker->removeToken($this->torrent, $this->user), 'tracker-remove-token');
    }

    /**
     * @group no-ci
     */
    public function testTrackerTorrent(): void {
        $this->user = Helper::makeUser('trk.' . randomString(10), 'tracker');
        $this->torrent = Helper::makeTorrentMusic(
            Helper::makeTGroupMusic(
                name:       'tracker ' . randomString(10),
                artistName: [[ARTIST_MAIN], ['Tracker Girl ' . randomString(12)]],
                tagName:    ['trap'],
                user:       $this->user,
            ),
            user:  $this->user,
            title: 'tracker ' . randomString(10),
        );
        $tracker = new \Gazelle\Tracker;
        $this->assertTrue($tracker->addTorrent($this->torrent), 'tracker-add-torrent');
        $this->assertTrue($tracker->modifyTorrent($this->torrent, LeechType::Free), 'tracker-modify-torrent');
    }

    /**
     * @group no-ci
     */
    public function testTrackerUser(): void {
        $tracker = new \Gazelle\Tracker;
        $this->assertFalse($tracker->last_error(), 'tracker-init');

        $info = $tracker->info();
        $this->assertCount(18, $info, 'tracker-info');

        $this->user = Helper::makeUser('trk.' . randomString(10), 'tracker');
        $this->assertTrue($tracker->addUser($this->user), 'tracker-add-user');
        $this->assertEquals(
            [
                'id'        => $this->user->id(),
                'can_leech' => 1,
                'protected' => 0,
                'deleted'   => 0,
                'leeching'  => 0,
                'seeding'   => 0,
            ],
            $tracker->userReport($this->user),
            'tracker-user-report-ok'
        );

        $this->user->setField('can_leech', 0)->setField('Visible', '0')->modify();
        $this->assertTrue($tracker->refreshUser($this->user), 'tracker-refresh-user');
        $this->assertEquals(
            [
                'id'        => $this->user->id(),
                'can_leech' => 0,
                'protected' => 1,
                'deleted'   => 0,
                'leeching'  => 0,
                'seeding'   => 0,
            ],
            $tracker->userReport($this->user),
            'tracker-user-report-cannot-leech'
        );

        $announceKey = $this->user->announceKey();
        $this->user->setField('torrent_pass', randomString())->modify();
        $this->assertTrue($tracker->modifyPasskey($announceKey, $this->user->announceKey()), 'tracker-modify-announce-key');

        $this->assertTrue($tracker->removeUser($this->user), 'tracker-remove-user');

        $current = $tracker->info();
        $this->assertEquals($info['requests handled'] + 8, $current['requests handled'], 'tracker-requests-handled');
    }

    /**
     * @group no-ci
     */
    public function testTrackerWhitelist(): void {
        $tracker = new \Gazelle\Tracker;
        $peer = '#utest-' . randomString(10);

        $this->assertTrue($tracker->removeWhitelist($peer), 'tracker-whitelist-remove-inexistent');
        $this->assertTrue($tracker->addWhitelist($peer), 'tracker-whitelist-add-inexistent');
        $this->assertTrue($tracker->modifyWhitelist($peer, $peer . 'Z'), 'tracker-whitelist-modify');

        $this->assertTrue($tracker->removeWhitelist($peer), 'tracker-whitelist-remove-1');
        $this->assertTrue($tracker->removeWhitelist($peer . 'Z'), 'tracker-whitelist-remove-2');
    }

    public function testTrackerExpireFreeleech(): void {
        $tracker = new \Gazelle\Tracker;
        $this->user = Helper::makeUser('trkfree.' . randomString(10), 'tracker');
        $this->torrent = Helper::makeTorrentMusic(
            Helper::makeTGroupMusic(
                name:       'tracker ' . randomString(10),
                artistName: [[ARTIST_MAIN], ['Tracker Girl ' . randomString(12)]],
                tagName:    ['trap'],
                user:       $this->user,
            ),
            user:  $this->user,
            title: 'tracker ' . randomString(10),
        );

        $downloader = Helper::makeUser('trkdown.' . randomString(10), 'tracker');
        $downloader->updateTokens(10);
        $download = new Gazelle\Download($this->torrent, new \Gazelle\User\UserclassRateLimit($downloader), true);
        $this->assertEquals(DownloadStatus::ok, $download->status(), 'tracker-downloader-enough-tokens');

        $userId    = $downloader->id();
        $torrentId = $this->torrent->id();
        $fakeId    = $torrentId + 1;

        $this->assertEquals(1, $tracker->expireFreeleechTokens("$userId:$torrentId,$userId:$fakeId"), 'tracker-expire-tokens');
        $downloader->remove();
    }
}
