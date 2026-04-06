<?php

namespace Gazelle;

use PHPUnit\Framework\TestCase;
use GazelleUnitTest\Helper;

class LogTest extends TestCase {
    protected const PREFIX = 'phpunit-logtest ';

    protected TGroup $tgroup;
    protected TGroup $tgroupNew;
    protected User   $user;

    public function tearDown(): void {
        $pg = new DB\Pg(PG_RW_DSN);
        $pg->query("
            delete from site_log where note ~ $1
            ", '^' . self::PREFIX
        );
        $pg->query("
            delete from audit_trail_tgroup where note ~ $1
            ", '^' . self::PREFIX
        );
        $db = DB::DB();
        $db->prepared_query("
            DELETE FROM group_log WHERE Info REGEXP ?
            ", '^' . self::PREFIX
        );
        if (isset($this->user)) {
            Helper::removeUser($this->user);
        }
    }

    public function testGeneralLog(): void {
        $siteLog = new Manager\SiteLog();
        $message = self::PREFIX . "general torrent 123457890 " . randomString();
        $siteLog->logger()->general($message);

        $result = $siteLog->page(1, 0, '');
        $latest = current($result);
        $this->assertEquals(['id', 'class', 'message', 'created'], array_keys($latest), 'sitelog-latest-keys');
        $this->assertStringContainsString(
            '<a href="torrents.php?torrentid=123457890">123457890</a>',
            $latest['message'],
            'sitelog-latest-decorated'
        );
        $this->assertFalse($latest['class'], 'sitelog-latest-decorated');
    }

    public function testGroupLog(): void {
        $this->user = Helper::makeUser('sitelog.' . randomString(6), 'sitelog');
        $this->user->requestContext()->setViewer($this->user);
        $logger = $this->user->logger();
        $this->tgroup = Helper::makeTGroupMusic(
            $this->user,
            'phpunit log ' . randomString(6),
            [[ARTIST_MAIN], ['phpunit log artist ' . randomString(6)]],
            ['log.jam']
        );
        $tgroupId = $this->tgroup->id;
        $logger->group($this->tgroup, $this->user, self::PREFIX . "group first " . randomString());

        $siteLog = new Manager\SiteLog();
        $this->assertCount(2, $siteLog->tgroupLogList($this->tgroup), 'grouplog-intial');
        $result = $siteLog->tgroupLogList($this->tgroup);
        $latest = current($result);
        $this->assertEqualsCanonicalizing(
            ["id_torrent", "id_user", "note", "created", "media", "format", "encoding", "deleted"],
            array_keys($latest),
            'grouplog-latest-keys'
        );
        $this->assertEquals(true, $latest['deleted'], 'grouplog-latest-is-deleted');
        $this->assertEquals(0, $latest['id_torrent'], 'grouplog-latest-no-torrent-id');

        $this->tgroupNew = Helper::makeTGroupMusic(
            $this->user,
            'phpunit log ' . randomString(6),
            [[ARTIST_MAIN], ['phpunit log artist ' . randomString(6)]],
            ['log.jam']
        );
        $logger->group($this->tgroup, null, self::PREFIX . "group extra " . randomString());
        $logger->group($this->tgroupNew, null, self::PREFIX . "group merge " . randomString());
        $this->assertEquals(3, $logger->merge($this->tgroup, $this->tgroupNew), 'grouplog-merge-result');

        $messageList = array_map(
            fn ($m) => $m['note'],
            $siteLog->tgroupLogList($this->tgroupNew),
        );

        $this->assertStringStartsWith(self::PREFIX . 'group merge ', $messageList[0], 'grouplog-merge-line-0');
        $this->assertStringStartsWith(self::PREFIX . 'group extra ', $messageList[1], 'grouplog-merge-line-1');
        $this->assertStringStartsWith('Added artist ', $messageList[2], 'grouplog-merge-line-2');
        $this->assertStringStartsWith(self::PREFIX . 'group first ', $messageList[3], 'grouplog-merge-line-3');
        $this->assertStringStartsWith('Added artist ', $messageList[4], 'grouplog-merge-line-4');
    }

    public function testTorrentlLog(): void {
        $this->user = Helper::makeUser('sitelog.' . randomString(6), 'sitelog');
        $this->user->requestContext()->setViewer($this->user);
        $logger = $this->user->logger();
        $this->tgroup = Helper::makeTGroupMusic(
            $this->user,
            'phpunit log ' . randomString(6),
            [[ARTIST_MAIN], ['phpunit log artist ' . randomString(6)]],
            ['log.jam']
        );
        $torrent = Helper::makeTorrentMusic(
            tgroup: $this->tgroup,
            user:  $this->user,
            title: randomString(10),
        );
        $logger->torrent($torrent, $this->user, self::PREFIX . "torrent " . randomString());

        $siteLog = new Manager\SiteLog();
        $this->assertCount(2, $siteLog->tgroupLogList($this->tgroup), 'torrentlog-has-log');

        $torrent->removeTorrent($this->user, 'phpunit log delete');
        $result = $siteLog->tgroupLogList($this->tgroup);
        $latest = current($result);
        $this->assertEquals(true, $latest['deleted'], 'torrentlog-latest-is-deleted');
        $this->assertEquals($torrent->id, $latest['id_torrent'], 'torrentlog-latest-torrent-id');
        $this->assertEquals($this->user->id, $latest['id_user'], 'torrentlog-latest-user-id');
    }

    public function testRenderLog(): void {
        $this->user = Helper::makeUser('sitelog.' . randomString(6), 'sitelog');
        $logger = $this->user->logger();
        $message = self::PREFIX . "general " . randomString();
        $logger->general($message);

        $siteLog   = new Manager\SiteLog();
        $paginator = new Util\Paginator(LOG_ENTRIES_PER_PAGE, 1);
        $page      = $siteLog->page($paginator->page(), $paginator->offset(), '');
        $paginator->setTotal($siteLog->total(''));

        Base::setRequestContext(new RequestContext('/index.php', '127.0.0.1', ''));
        global $SessionID;
        $SessionID = 'phpunit';
        Util\Twig::setViewer($this->user);
        $html = Util\Twig::factory(new Manager\User())->render('sitelog.twig', [
            'search'    => '',
            'paginator' => $paginator,
            'page'      => $page,
        ]);
        $this->assertStringContainsString($message, $html, 'sitelog-render');
    }
}
