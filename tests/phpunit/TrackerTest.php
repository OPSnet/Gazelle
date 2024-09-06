<?php

namespace Gazelle;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Group;
use Gazelle\Enum\DownloadStatus;
use Gazelle\Enum\LeechType;

class TrackerTest extends TestCase {
    protected Torrent $torrent;
    protected User    $user;

    public function tearDown(): void {
        if (isset($this->torrent)) {
            \GazelleUnitTest\Helper::removeTGroup($this->torrent->group(), $this->user);
        }
        if (isset($this->user)) {
            $this->user->remove();
        }
    }

    #[Group('no-ci')]
    public function testTrackerAnnounce(): void {
        $tracker  = new Tracker();
        $interval = 1000 + random_int(0, 2000);
        $jitter   = random_int(0, 500);
        $this->assertTrue($tracker->modifyAnnounceInterval($interval), 'tracker-announce-modify-interval');
        $this->assertTrue($tracker->modifyAnnounceJitter($jitter), 'tracker-announce-modify-jitter');

        $info = $tracker->info();
        $this->assertEquals($interval, $info['announce interval']['value'], 'tracker-announce-interval');
        $this->assertEquals($jitter, $info['announce jitter']['value'], 'tracker-announce-jitter');
    }

    #[Group('no-ci')]
    public function testTrackerToken(): void {
        $this->user = \GazelleUnitTest\Helper::makeUser('trkfl.' . randomString(10), 'tracker');
        $this->torrent = \GazelleUnitTest\Helper::makeTorrentMusic(
            \GazelleUnitTest\Helper::makeTGroupMusic(
                name:       'tracker ' . randomString(10),
                artistName: [[ARTIST_MAIN], ['Tracker Girl ' . randomString(12)]],
                tagName:    ['trap'],
                user:       $this->user,
            ),
            user:  $this->user,
            title: 'tracker ' . randomString(10),
        );
        $tracker = new Tracker();
        $this->assertTrue($tracker->addTorrent($this->torrent), 'tracker-add-torrent');
        $this->assertTrue($tracker->addToken($this->torrent, $this->user), 'tracker-add-token');
        $report = $tracker->torrentReport($this->torrent);
        $this->assertCount(9, array_keys($report), 'tracker-tinfo-count');
        $this->assertEquals($this->torrent->id(), $report['id'], 'tracker-tinfo-id');
        $this->assertCount(0, $report['leecher_list'], 'tracker-tinfo-leecher');
        $this->assertCount(0, $report['seeder_list'], 'tracker-tinfo-seeder');
        $this->assertEquals([$this->user->id()], $report['fltoken_list'], 'tracker-tinfo-fltoken');
        $this->assertTrue($tracker->removeToken($this->torrent, $this->user), 'tracker-remove-token');
    }

    #[Group('no-ci')]
    public function testTrackerTorrent(): void {
        $this->user = \GazelleUnitTest\Helper::makeUser('trk.' . randomString(10), 'tracker');
        $this->torrent = \GazelleUnitTest\Helper::makeTorrentMusic(
            \GazelleUnitTest\Helper::makeTGroupMusic(
                name:       'tracker ' . randomString(10),
                artistName: [[ARTIST_MAIN], ['Tracker Girl ' . randomString(12)]],
                tagName:    ['trap'],
                user:       $this->user,
            ),
            user:  $this->user,
            title: 'tracker ' . randomString(10),
        );
        $tracker = new Tracker();
        $this->assertTrue($tracker->addTorrent($this->torrent), 'tracker-add-torrent');
        $this->assertTrue($tracker->modifyTorrent($this->torrent, LeechType::Free), 'tracker-modify-torrent');
    }

    #[Group('no-ci')]
    public function testTrackerUser(): void {
        $tracker = new Tracker();
        $info = $tracker->info();
        $this->assertFalse($tracker->lastError(), 'tracker-init');

        $this->user = \GazelleUnitTest\Helper::makeUser('trk.' . randomString(10), 'tracker');
        $this->assertEquals(
            [
                'id'        => $this->user->id(),
                'can_leech' => 1,
                'protected' => 0,
                'deleted'   => 0,
                'leeching'  => 0,
                'seeding'   => 0,
                'traced'    => 0,
            ],
            $tracker->userReport($this->user),
            'tracker-user-report-ok'
        );

        $this->user->setField('can_leech', 0)->setField('Visible', '0')->modify();
        $this->assertTrue($tracker->refreshUser($this->user), 'tracker-refresh-user');
        $this->assertTrue($tracker->traceUser($this->user, true), 'tracker-trace-user');
        $this->assertEquals(
            [
                'id'        => $this->user->id(),
                'can_leech' => 0,
                'protected' => 1,
                'deleted'   => 0,
                'leeching'  => 0,
                'seeding'   => 0,
                'traced'    => 1,
            ],
            $tracker->userReport($this->user),
            'tracker-user-report-cannot-leech'
        );

        $tracker->traceUser($this->user, false);
        $this->assertEquals(
            [
                'id'        => $this->user->id(),
                'can_leech' => 0,
                'protected' => 1,
                'deleted'   => 0,
                'leeching'  => 0,
                'seeding'   => 0,
                'traced'    => 0,
            ],
            $tracker->userReport($this->user),
            'tracker-user-report-untraced'
        );

        $announceKey = $this->user->announceKey();
        $this->user->setField('torrent_pass', randomString())->modify();
        $this->assertTrue($tracker->modifyPasskey($announceKey, $this->user->announceKey()), 'tracker-modify-announce-key');
        $this->assertTrue($tracker->removeUser($this->user), 'tracker-remove-user');

        $initial = current(array_filter($info, fn ($v) => $v['label'] == 'requests handled'))['value'];
        $current = $tracker->info();
        $this->assertEquals($info['requests handled']['value'] + 10, $current['requests handled']['value'], 'tracker-requests-handled');
    }

    #[Group('no-ci')]
    public function testTrackerWhitelist(): void {
        $tracker = new Tracker();
        $peer = '#utest-' . randomString(10);

        $this->assertTrue($tracker->removeWhitelist($peer), 'tracker-whitelist-remove-inexistent');
        $this->assertTrue($tracker->addWhitelist($peer), 'tracker-whitelist-add-inexistent');
        $this->assertTrue($tracker->modifyWhitelist($peer, $peer . 'Z'), 'tracker-whitelist-modify');

        $this->assertTrue($tracker->removeWhitelist($peer), 'tracker-whitelist-remove-1');
        $this->assertTrue($tracker->removeWhitelist($peer . 'Z'), 'tracker-whitelist-remove-2');
    }

    public function testTrackerExpireFreeleech(): void {
        $tracker = new Tracker();
        $this->user = \GazelleUnitTest\Helper::makeUser('trkfree.' . randomString(10), 'tracker');
        $this->torrent = \GazelleUnitTest\Helper::makeTorrentMusic(
            \GazelleUnitTest\Helper::makeTGroupMusic(
                name:       'tracker ' . randomString(10),
                artistName: [[ARTIST_MAIN], ['Tracker Girl ' . randomString(12)]],
                tagName:    ['trap'],
                user:       $this->user,
            ),
            user:  $this->user,
            title: 'tracker ' . randomString(10),
        );
        $tracker->addTorrent($this->torrent);

        $downloader = \GazelleUnitTest\Helper::makeUser('trkdown.' . randomString(10), 'tracker');
        $downloader->updateTokens(10);
        $download = new Download($this->torrent, new User\UserclassRateLimit($downloader), true);
        $this->assertEquals(DownloadStatus::ok, $download->status(), 'tracker-downloader-enough-tokens');

        $userId    = $downloader->id();
        $torrentId = $this->torrent->id();
        $fakeId    = $torrentId + 1;

        $this->assertEquals(1, $tracker->expireFreeleechTokens("$userId:$torrentId,$userId:$fakeId"), 'tracker-expire-tokens');
        $downloader->remove();
    }
}
