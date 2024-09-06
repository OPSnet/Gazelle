<?php

namespace Gazelle;

use PHPUnit\Framework\TestCase;

class LogTest extends TestCase {
    protected const PREFIX = 'phpunit-logtest ';

    protected TGroup $tgroup;
    protected TGroup $tgroupNew;
    protected User   $user;

    public function tearDown(): void {
        $db = DB::DB();
        $db->prepared_query("
            DELETE FROM log WHERE Message REGEXP ?
            ", '^' . self::PREFIX
        );
        $db->prepared_query("
            DELETE FROM group_log WHERE Info REGEXP ?
            ", '^' . self::PREFIX
        );
        if (isset($this->tgroup)) {
            $this->tgroup->remove($this->user);
        }
        if (isset($this->tgroupNew)) {
            $this->tgroupNew->remove($this->user);
        }
        if (isset($this->user)) {
            $this->user->remove();
        }
    }

    public function testGeneralLog(): void {
        $logger = new Log();
        $message = self::PREFIX . "general " . randomString();
        $logger->general($message);

        $sitelog = new Manager\SiteLog(new Manager\User());
        $this->assertInstanceOf(Manager\SiteLog::class, $sitelog, 'sitelog-manager');
        $result = $sitelog->page(1, 0, '');
        $latest = current($result);
        $this->assertEquals(['id', 'class', 'message', 'created'], array_keys($latest), 'sitelog-latest-keys');
        $this->assertEquals($message, $latest['message'], 'sitelog-latest-message');
        $this->assertFalse($latest['class'], 'sitelog-latest-decorated');
    }

    public function testGroupLog(): void {
        $logger = new Log();
        $this->user = \GazelleUnitTest\Helper::makeUser('sitelog.' . randomString(6), 'sitelog');
        $this->tgroup = \GazelleUnitTest\Helper::makeTGroupMusic(
            $this->user,
            'phpunit log ' . randomString(6),
            [[ARTIST_MAIN], ['phpunit log artist ' . randomString(6)]],
            ['log.jam']
        );
        $tgroupId = $this->tgroup->id();
        $logger->group($this->tgroup, $this->user, self::PREFIX . "group first " . randomString());

        $sitelog = new Manager\SiteLog(new Manager\User());
        $this->assertCount(2, $sitelog->tgroupLogList($tgroupId), 'grouplog-intial');
        $result = $sitelog->tgroupLogList($tgroupId);
        $latest = current($result);
        $this->assertEquals(
            ["torrent_id", "user_id", "info", "created", "media", "format", "encoding", "deleted"],
            array_keys($latest),
            'grouplog-latest-keys'
        );
        $this->assertEquals(1, $latest['deleted'], 'grouplog-latest-is-deleted');
        $this->assertEquals(0, $latest['torrent_id'], 'grouplog-latest-no-torrent-id');

        $this->tgroupNew = \GazelleUnitTest\Helper::makeTGroupMusic(
            $this->user,
            'phpunit log ' . randomString(6),
            [[ARTIST_MAIN], ['phpunit log artist ' . randomString(6)]],
            ['log.jam']
        );
        $logger->group($this->tgroup, null, self::PREFIX . "group extra " . randomString());
        $logger->group($this->tgroupNew, null, self::PREFIX . "group merge " . randomString());
        $this->assertEquals(3, $logger->merge($this->tgroup, $this->tgroupNew), 'grouplog-merge-result');

        $messageList = array_map(
            fn($m) => $m['info'],
            $sitelog->tgroupLogList($this->tgroupNew->id()),
        );

        $this->assertStringStartsWith(self::PREFIX . 'group merge ', $messageList[0], 'grouplog-merge-line-0');
        $this->assertStringStartsWith(self::PREFIX . 'group extra ', $messageList[1], 'grouplog-merge-line-1');
        $this->assertStringStartsWith('Added artist ', $messageList[2], 'grouplog-merge-line-2');
        $this->assertStringStartsWith(self::PREFIX . 'group first ', $messageList[3], 'grouplog-merge-line-3');
        $this->assertStringStartsWith('Added artist ', $messageList[4], 'grouplog-merge-line-4');
    }

    public function testTorrentlLog(): void {
        $logger = new Log();
        $this->user = \GazelleUnitTest\Helper::makeUser('sitelog.' . randomString(6), 'sitelog');
        $this->tgroup = \GazelleUnitTest\Helper::makeTGroupMusic(
            $this->user,
            'phpunit log ' . randomString(6),
            [[ARTIST_MAIN], ['phpunit log artist ' . randomString(6)]],
            ['log.jam']
        );
        $torrent = \GazelleUnitTest\Helper::makeTorrentMusic(
            tgroup: $this->tgroup,
            user:  $this->user,
            title: randomString(10),
        );
        $logger->torrent($torrent, $this->user, self::PREFIX . "torrent " . randomString());

        $sitelog = new Manager\SiteLog(new Manager\User());
        $this->assertCount(2, $sitelog->tgroupLogList($this->tgroup->id()), 'torrentlog-has-log');

        $torrent->remove($this->user, 'phpunit log delete');
        $result = $sitelog->tgroupLogList($this->tgroup->id());
        $latest = current($result);
        $this->assertEquals(1, $latest['deleted'], 'torrentlog-latest-is-deleted');
        $this->assertEquals($torrent->id(), $latest['torrent_id'], 'torrentlog-latest-torrent-id');
        $this->assertEquals($this->user->id(), $latest['user_id'], 'torrentlog-latest-user-id');
    }

    public function testRenderLog(): void {
        $message = self::PREFIX . "general " . randomString();
        (new Log())->general($message);

        $sitelog   = new Manager\SiteLog(new Manager\User());
        $paginator = new Util\Paginator(LOG_ENTRIES_PER_PAGE, 1);
        $page      = $sitelog->page($paginator->page(), $paginator->offset(), '');
        $paginator->setTotal($sitelog->totalMatches());

        // FIXME: $Viewer should not be necessary
        $this->user = \GazelleUnitTest\Helper::makeUser('sitelog.' . randomString(6), 'sitelog');
        Base::setRequestContext(new BaseRequestContext('/index.php', '127.0.0.1', ''));
        global $Viewer;
        $Viewer = $this->user;
        global $SessionID;
        $SessionID = 'phpunit';
        $html = (Util\Twig::factory())->render('sitelog.twig', [
            'search'    => '',
            'paginator' => $paginator,
            'page'      => $page,
        ]);
        $this->assertStringContainsString($message, $html, 'sitelog-render');
    }
}
