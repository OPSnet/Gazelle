<?php

use PHPUnit\Framework\TestCase;
use Gazelle\Enum\TorrentFlag;

require_once(__DIR__ . '/../../lib/bootstrap.php');
require_once(__DIR__ . '/../helper.php');

class TorrentTest extends TestCase {
    protected \Gazelle\Torrent $torrent;
    protected \Gazelle\User    $user;

    public function setUp(): void {
        $this->user    = Helper::makeUser('torrent.' . randomString(10), 'rent');
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
    }

    public function testFlag(): void {
        $this->assertFalse($this->torrent->hasFlag(TorrentFlag::badFile), 'torrent-no-bad-file-flag');
        $this->assertEquals(1, $this->torrent->addFlag(TorrentFlag::badFile, $this->user), 'torrent-add-bad-file-flag');
        $this->assertTrue($this->torrent->hasFlag(TorrentFlag::badFile), 'torrent-has-bad-file-flag');

        $this->assertEquals(1, $this->torrent->removeFlag(TorrentFlag::badFile), 'torrent-remove-bad-file-flag');
        $this->assertEquals(0, $this->torrent->removeFlag(TorrentFlag::badFolder), 'torrent-remove-no-flag');
        $this->assertFalse($this->torrent->hasFlag(TorrentFlag::badFile), 'torrent-no-more--bad-file-flag');
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
