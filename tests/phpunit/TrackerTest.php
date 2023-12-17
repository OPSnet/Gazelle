<?php

use PHPUnit\Framework\TestCase;
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
        $this->assertCount(13, $info, 'tracker-info');

        $this->user = Helper::makeUser('trk.' . randomString(10), 'tracker');
        $this->assertTrue($tracker->addUser($this->user), 'tracker-add-user');
        $this->assertEquals(
            ['leeching' => 0, 'seeding' => 0],
            $tracker->user_peer_count($this->user),
            'tracker-global-peer-count'
        );
        $this->user->setField('can_leech', 0)->modify();
        $this->assertTrue($tracker->refreshUser($this->user), 'tracker-refresh-user');

        $announceKey = $this->user->announceKey();
        $this->user->setField('torrent_pass', randomString())->modify();
        $this->assertTrue($tracker->modifyPasskey($announceKey, $this->user->announceKey()), 'tracker-modify-announce-key');

        $this->assertTrue($tracker->removeUser($this->user), 'tracker-remove-user');

        $current = $tracker->info();
        $this->assertEquals($info['requests handled'] + 7, $current['requests handled'], 'tracker-requests-handled');
    }

    /**
     * @group no-ci
     */
    public function testTrackerUntracked(): void {
        $tracker = new \Gazelle\Tracker;
        $this->user = Helper::makeUser('trknope.' . randomString(10), 'tracker');
        $this->assertEquals(
            ['leeching' => 0, 'seeding' => 0],
            $tracker->user_peer_count($this->user),
            'tracker-global-untracked-peer-count'
        );
    }
}
