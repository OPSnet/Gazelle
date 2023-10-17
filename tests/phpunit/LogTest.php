<?php

use PHPUnit\Framework\TestCase;

require_once(__DIR__ . '/../../lib/bootstrap.php');
require_once(__DIR__ . '/../helper.php');

class LogTest extends TestCase {
    protected const PREFIX = 'phpunit-logtest ';
    protected \Gazelle\User $user;

    public function tearDown(): void {
        $db = \Gazelle\DB::DB();
        $db->prepared_query("
            DELETE FROM log WHERE Message REGEXP ?
            ", '^' . self::PREFIX
        );
        $db->prepared_query("
            DELETE FROM group_log WHERE Info REGEXP ?
            ", '^' . self::PREFIX
        );
        if (isset($this->user)) {
            $this->user->remove();
        }
    }

    public function testGeneralLog(): void {
        $logger = new \Gazelle\Log;
        $message = self::PREFIX . "general " . randomString();
        $logger->general($message);

        $sitelog = new \Gazelle\Manager\SiteLog(new \Gazelle\Manager\User);
        $this->assertInstanceOf(\Gazelle\Manager\SiteLog::class, $sitelog, 'sitelog-manager');
        $result = $sitelog->page(1, 0, '');
        $latest = current($result);
        $this->assertEquals(['id', 'color', 'message', 'created'], array_keys($latest), 'sitelog-latest-keys');
        $this->assertEquals($message, $latest['message'], 'sitelog-latest-message');
        $this->assertFalse($latest['color'], 'sitelog-latest-decorated');
    }

    public function testGroupLog(): void {
        $logger = new \Gazelle\Log;
        $logger->group(-1, 0, self::PREFIX . "group first " . randomString());

        $sitelog = new \Gazelle\Manager\SiteLog(new \Gazelle\Manager\User);
        $this->assertCount(0, $sitelog->tgroupLogList(-101), 'grouplog-no-log');
        $result = $sitelog->tgroupLogList(-1);
        $latest = current($result);
        $this->assertEquals(
            ["torrent_id", "user_id", "info", "created", "media", "format", "encoding", "deleted"],
            array_keys($latest),
            'grouplog-latest-keys'
        );
        $this->assertEquals(1, $latest['deleted'], 'grouplog-latest-is-deleted');
        $this->assertEquals(0, $latest['torrent_id'], 'grouplog-latest-no-torrent-id');

        $logger->group(-1, 0, self::PREFIX . "group extra " . randomString());
        $logger->group(-2, 0, self::PREFIX . "group merge " . randomString());
        $this->assertEquals(2, $logger->merge(-1, -2), 'grouplog-merge-result');

        $messageList = array_map(
            fn($m) => $m['info'],
            $sitelog->tgroupLogList(-2),
        );
        $this->assertStringContainsString(self::PREFIX . 'group first ', $messageList[2], 'grouplog-merge-line-0');
        $this->assertStringContainsString(self::PREFIX . 'group extra ', $messageList[1], 'grouplog-merge-line-1');
        $this->assertStringContainsString(self::PREFIX . 'group merge ', $messageList[0], 'grouplog-merge-line-2');
    }

    public function testTorrentlLog(): void {
        $logger = new \Gazelle\Log;
        $logger->torrent(-3, -30, -5, self::PREFIX . "torrent " . randomString());

        $sitelog = new \Gazelle\Manager\SiteLog(new \Gazelle\Manager\User);
        $this->assertCount(0, $sitelog->tgroupLogList(-1001), 'torrentlog-no-log');
        $result = $sitelog->tgroupLogList(-3);
        $latest = current($result);
        $this->assertEquals(1, $latest['deleted'], 'torrentlog-latest-is-deleted');
        $this->assertEquals(-30, $latest['torrent_id'], 'torrentlog-latest-torrent-id');
        $this->assertEquals(-5, $latest['user_id'], 'torrentlog-latest-user-id');
    }

    public function testRenderLog(): void {
        $message = self::PREFIX . "general " . randomString();
        (new \Gazelle\Log)->general($message);

        $sitelog   = new \Gazelle\Manager\SiteLog(new \Gazelle\Manager\User);
        $paginator = new \Gazelle\Util\Paginator(LOG_ENTRIES_PER_PAGE, 1);
        $page      = $sitelog->page($paginator->page(), $paginator->offset(), '');
        $paginator->setTotal($sitelog->totalMatches());

        // FIXME: $Viewer should not be necessary
        $this->user = Helper::makeUser('sitelog.' . randomString(6), 'sitelog');
        global $Viewer;
        $Viewer = $this->user;
        global $Document;
        $Document = '';
        global $SessionID;
        $SessionID = 'phpunit';
        $html = (Gazelle\Util\Twig::factory())->render('sitelog.twig', [
            'search'    => '',
            'paginator' => $paginator,
            'page'      => $page,
        ]);
        $this->assertStringContainsString($message, $html, 'sitelog-render');
    }
}
