<?php

use PHPUnit\Framework\TestCase;
use Gazelle\NotificationTicketState;

require_once(__DIR__ . '/../../lib/bootstrap.php');
require_once(__DIR__ . '/../helper.php');

class NotificationUploadTest extends TestCase {
    use Gazelle\Pg;

    protected Gazelle\Manager\Torrent $torMan;
    protected Gazelle\Torrent         $torrent;
    protected array                   $userList;

    public function setUp(): void {
        $user = Helper::makeUser('uploader.' . randomString(10), 'notification-ticket');
        $tgroup = (new Gazelle\Manager\TGroup)->create(
            categoryId:      1,
            releaseType:     (new Gazelle\ReleaseType)->findIdByName('Compilation'),
            name:            'phpunit notify ' . randomString(6),
            description:     'phpunit notify description',
            image:           '',
            year:            2022,
            recordLabel:     'Unitest Artists Corporation',
            catalogueNumber: 'UA-246',
            showcase:        false,
        );
        $tgroup->addArtists([ARTIST_MAIN], ['Notify Man ' . randomString(12)], $user, new Gazelle\Manager\Artist, new Gazelle\Log);

        $tagMan = new Gazelle\Manager\Tag;
        $tagMan->createTorrentTag($tagMan->create('electronic', $user), $tgroup->id(), $user->id(), 10);
        $tagMan->createTorrentTag($tagMan->create('funk', $user), $tgroup->id(), $user->id(), 10);
        $tagMan->createTorrentTag($tagMan->create('jazz', $user), $tgroup->id(), $user->id(), 10);
        $tgroup->refresh();

        $this->torMan = new \Gazelle\Manager\Torrent;
        $this->torrent = $this->torMan->create(
            tgroup:                  $tgroup,
            user:                    $user,
            description:             'notify release description',
            media:                   'WEB',
            format:                  'FLAC',
            encoding:                'Lossless',
            infohash:                'infohash-' . randomString(10),
            filePath:                'unit-test',
            fileList:                [],
            size:                    123_456_789,
            isScene:                 false,
            isRemaster:              true,
            remasterYear:            2023,
            remasterTitle:           '',
            remasterRecordLabel:     'Unitest Artists',
            remasterCatalogueNumber: 'UA-NOTIF-1',
        );
    }

    public function tearDown(): void {
        $user = $this->torrent->uploader();
        Helper::removeTGroup($this->torrent->group(), $user);
        $user->remove();
        foreach ($this->userList as $user) {
            $user->remove();
        }
    }

    public function testNotificationUpload(): void {
        // Every notification needs a separate user because if one upload triggers
        // more than one notification filter, only one is acted upon.
        // In other words, if you want to add a new combination to check, you need
        // to create user here.
        $this->userList = [
            'artist'  => Helper::makeUser('artist.' . randomString(10), 'notification-ticket'),
            'enc.med' => Helper::makeUser('enc.med.' . randomString(10), 'notification-ticket'),
            'release' => Helper::makeUser('release.' . randomString(10), 'notification-ticket'),
            'tag'     => Helper::makeUser('tag.' . randomString(10), 'notification-ticket'),
            'tag2yes' => Helper::makeUser('tag2yes.' . randomString(10), 'notification-ticket'),
            'tag2no'  => Helper::makeUser('tag2no.' . randomString(10), 'notification-ticket'),
            'tagno'   => Helper::makeUser('tagno.' . randomString(10), 'notification-ticket'),
            'user'    => Helper::makeUser('user.' . randomString(10), 'notification-ticket'),
            'xva'     => Helper::makeUser('xva.' . randomString(10), 'notification-ticket'),
            'year'    => Helper::makeUser('year.' . randomString(10), 'notification-ticket'),
        ];

        // create some notification filters for the users
        $artistName = $this->torrent->group()->artistRole()->idList()[ARTIST_MAIN][0]['name'];
        $artistFilter = (new Gazelle\Notification\Filter)
            ->setLabel('Artists')
            ->setMultiLine('artist', $artistName);
        $this->assertTrue($artistFilter->isConfigured(), 'filter-artist-configured');
        $filter['artist'] = $artistFilter->create($this->userList['artist']->id());
        $this->assertGreaterThan(0, $filter['artist'], 'filter-artist-created');
        $nextFilter = $filter['artist'] + 1;

        // encoding+media
        $this->assertEquals($nextFilter++,
            $filter['enc.med'] = (new Gazelle\Notification\Filter)
                ->setLabel('Format+Media')
                ->setMultiLine('artist', '') // TODO: INSERT fails on not null assertion
                ->setMultiValue('encoding', ['Lossless', '24bit Lossless'])
                ->setMultiValue('media',  ['WEB', 'CD'])
                ->create($this->userList['enc.med']->id()),
            'filter-enc.med-created'
        );

        // exclude VA (Compilation)
        $this->assertEquals($nextFilter++,
            $filter['xva'] = (new Gazelle\Notification\Filter)
                ->setLabel('No compilations')
                ->setMultiLine('artist', '')
                ->setBoolean('exclude_va', true)
                ->create($this->userList['xva']->id()),
            'filter-exclude-va-created'
        );

        // release type Album
        $this->assertEquals($nextFilter++,
            $filter['release'] = (new Gazelle\Notification\Filter)
                ->setLabel('Release')
                ->setMultiLine('artist', '')
                ->setMultiLine('release_type', "Single")
                ->create($this->userList['release']->id()),
            'filter-tag-created'
        );

        // one tag present
        $this->assertEquals($nextFilter++,
            $filter['tag'] = (new Gazelle\Notification\Filter)
                ->setLabel('Tags')
                ->setMultiLine('artist', '')
                ->setMultiLine('tag', "electronic\n\n")
                ->create($this->userList['tag']->id()),
            'filter-tag-created'
        );

        // two tags present
        $this->assertEquals($nextFilter++,
            $filter['tag2yes'] = (new Gazelle\Notification\Filter)
                ->setLabel('Two Tags')
                ->setMultiLine('artist', '')
                ->setMultiLine('tag', "jazz\nelectronic\n")
                ->create($this->userList['tag2yes']->id()),
            'filter-two-tag-created'
        );

        // no tags present
        $this->assertEquals($nextFilter++,
            $filter['tag2no'] = (new Gazelle\Notification\Filter)
                ->setLabel('Two Tags+')
                ->setMultiLine('artist', '')
                ->setMultiLine('tag', "jazz.rock\nhard.bop\n")
                ->create($this->userList['tag2no']->id()),
            'filter-two-tag-no-created'
        );

        // these are not the tags you are looking for
        $this->assertEquals($nextFilter++,
            $filter['tagno'] = (new Gazelle\Notification\Filter)
                ->setLabel('Not these tags')
                ->setMultiLine('artist', '')
                ->setMultiLine('not_tag', "funk\nfolk\n")
                ->create($this->userList['tagno']->id()),
            'filter-not-tag-created'
        );

        // uploads by user
        $this->assertEquals($nextFilter++,
            $filter['user'] = (new Gazelle\Notification\Filter)
                ->setLabel('Users')
                ->setMultiLine('artist', '')
                ->setUsers(new Gazelle\Manager\User, $this->torrent->uploader()->username())
                ->create($this->userList['user']->id()),
            'filter-users-created'
        );

        // category Music
        $this->assertEquals($nextFilter++,
            $filter['user.self'] = (new Gazelle\Notification\Filter)
                ->setLabel('Tags')
                ->setMultiLine('artist', '')
                ->setMultiLine('category', "Music")
                ->create($this->torrent->uploaderId()),
            'filter-self-created'
        );

        // in some year
        $this->assertEquals($nextFilter++,
            $filter['year'] = (new Gazelle\Notification\Filter)
                ->setLabel('Year')
                ->setMultiLine('artist', '')
                ->setYears(2020, 2022)
                ->create($this->userList['year']->id()),
            'filter-year-created'
        );

        // create a ticket for this new torrent
        $ticketManager = new Gazelle\Manager\NotificationTicket;
        $ticket = $ticketManager->create($this->torrent);
        $this->assertInstanceOf(Gazelle\NotificationTicket::class, $ticket, 'we-haz-notification-ticket');
        $this->assertEquals($ticket->created(), $ticket->modified(), 'ntick-new');
        $this->assertEquals(NotificationTicketState::Pending, $ticket->state(), 'ntick-state');

        // it should be pending, so make it active
        $this->assertNull($ticketManager->findByExclusion(NotificationTicketState::Pending, exclude: [$this->torrent->id()]), 'ntick-pending-exclude');
        $ticket = $ticketManager->findByExclusion(NotificationTicketState::Pending, exclude: []);
        $this->assertEquals($this->torrent->id(), $ticket->torrentId(), 'ntick-pending-torrent-id');
        $ticket->setActive();
        $this->assertEquals($ticket->state(), NotificationTicketState::Active, 'ntick-active-value');
        $this->assertEquals(1, (new Gazelle\Manager\Notification)->ticketStats()['active']['total'], 'notifier-ticket-stats-now-active');

        // send the IRC notification
        $notification = new Gazelle\Notification\Upload($this->torMan->findById($ticket->torrentId()));
        $message = $notification->ircNotification();
        $this->assertStringContainsString($this->torrent->group()->name(), $message, 'ntick-irc-tgroup-name');
        $this->assertStringContainsString(implode(',', $this->torrent->group()->tagNameList()), $message, 'ntick-irc-tgroup-taglist');
        $this->assertStringContainsString("[{$this->torrent->group()->releaseTypeName()}]", $message, 'ntick-irc-release-type');

        // look at the conditions to be met for a notification from this upload
        $notification = new Gazelle\Notification\Upload($this->torrent);
        $this->assertEquals(11, $notification->configure(), 'notif-configured');
        $condition = $notification->cond();
        $this->assertIsInt(array_search($artistName, $notification->args()), 'notif-args-artist');
        $this->assertIsInt(array_search('Music', $notification->args()), 'notif-args-category');
        $this->assertIsInt(array_search('Lossless', $notification->args()), 'notif-args-encoding');
        $this->assertIsInt(array_search('FLAC', $notification->args()), 'notif-args-format');
        $this->assertIsInt(array_search('WEB', $notification->args()), 'notif-args-media');
        $this->assertIsInt(array_search('Compilation', $notification->args()), 'notif-args-releaseType');
        $this->assertIsInt(array_search($this->torrent->uploaderId(), $notification->args()), 'notif-args-user');
        $this->assertIsInt(array_search($this->torrent->group()->year(), $notification->args()), 'notif-args-group-year');
        $this->assertIsInt(array_search($this->torrent->remasterYear(), $notification->args()), 'notif-args-remaster-year');
        foreach ($this->torrent->group()->tagNameList() as $tag) {
            $this->assertIsInt(array_search($tag, $notification->args()), "notif-args-tag-$tag");
        }

        // that looks good, what filters get triggered?
        $list = $notification->userFilterList();
        $filterList = array_column($list, 'filter_id');
        $this->assertIsInt(array_search($filter['artist'], $filterList), 'notif-caught-artist');
        $this->assertIsInt(array_search($filter['enc.med'], $filterList), 'notif-caught-format-encoding');
        $this->assertIsInt(array_search($filter['tag'], $filterList), 'notif-caught-tag-1');
        $this->assertIsInt(array_search($filter['tag2yes'], $filterList), 'notif-caught-tag-both');
        $this->assertIsInt(array_search($filter['user'], $filterList), 'notif-caught-user');
        $this->assertIsInt(array_search($filter['year'], $filterList), 'notif-caught-year');

        // total equals number of notif-caught-* assertions above
        $this->assertEquals(6, $notification->sendUserNotification(), 'notif-send-user-caught');

        // these don't
        $this->assertFalse(array_search($filter['release'], $filterList), 'notif-skipped-release-single');
        $this->assertFalse(array_search($filter['tagno'], $filterList), 'notif-skipped-tagno');
        $this->assertFalse(array_search($filter['tag2no'], $filterList), 'notif-skipped-tag2no');
        $this->assertFalse(array_search($filter['user.self'], $filterList), 'notif-skipped-self');
        $this->assertFalse(array_search($filter['xva'], $filterList), 'notif-skipped-exclude-va');

        // remove someone's filter
        $userFilter = $this->userList['release']->notifyFilters();
        $this->assertCount(1, $userFilter, 'user-has-a-filter');
        $this->assertEquals(0, array_search($filter['release'], $userFilter), 'user-has-this-filter');
        $this->assertEquals(1, $this->userList['release']->removeNotificationFilter($filter['release']), 'user-remove-own-filter');
        $this->assertEquals(0, $this->userList['release']->removeNotificationFilter($filter['tag']), 'user-remove-other-filter');

        // look for an unread user notification
        $notifier = new Gazelle\Notification\Torrent($this->userList['artist']->id());
        $this->assertEquals(1, $notifier->total(), 'notifier-artist-total');
        $unreadList = $notifier->unreadList(1, 0);
        $this->assertCount(1, $unreadList, 'notifier-artist-unread-list');
        $this->assertEquals($this->torrent->id(), $unreadList[0]['torrentId'], 'notifier-artist-unread-torrent-id');

        // catch notifications
        unset($notifier);
        $notifier = new Gazelle\Notification\Torrent($this->userList['enc.med']->id());
        $this->assertEquals(1, $notifier->catchup(), 'notifier-encmed-catchup');
        $this->assertCount(1, $notifier->unreadList(1, 0), 'notifier-encmed-unread'); // FIXME: it's actually read+unread

        // clear unread user notifications
        unset($notifier);
        $notifier = new Gazelle\Notification\Torrent($this->userList['tag']->id());
        $this->assertEquals(1, $notifier->catchupFilter($filter['tag']), 'notifier-tag-catchup-filter');
        $this->assertCount(1, $notifier->unreadList(1, 0), 'notifier-tag-catchup-unread');
        $this->assertEquals(1, $notifier->clearFilter($filter['tag']), 'notifier-tag-clear-filter');
        $this->assertCount(0, $notifier->unreadList(1, 0), 'notifier-tag-clear-unread');
    }

    public function testHandle(): void {
        // create a user and a notification filter
        $this->userList = [
            'record.label' => Helper::makeUser('reclab.' . randomString(10), 'notification-ticket'),
        ];
        $filter = (new Gazelle\Notification\Filter)
            ->setLabel('Record Labels')
            ->setMultiLine('artist', '') // TODO: INSERT fails on not null assertion
            ->setMultiLine('record.label', "Unitest Artists Corporation")
            ->create($this->userList['record.label']->id());

        // create the ticket and pretend it is seeding
        $ticketManager = new Gazelle\Manager\NotificationTicket;
        $ticket = $ticketManager->create($this->torrent);
        Gazelle\DB::DB()->prepared_query("
            INSERT INTO xbt_files_users
                   (fid, uid, useragent, peer_id, active, remaining, ip, timespent, mtime)
            VALUES (?,   ?,   ?,         ?,       1, 0, '127.0.0.1', 1, unix_timestamp(now() - interval 5 second))
            ",  $this->torrent->id(), $this->torrent->uploaderId(), 'ua-' . randomString(12), randomString(20)
        );

        // handle the ticket
        $manager = new Gazelle\Manager\Notification;
        $this->assertTrue($manager->handleTicket($ticket, $this->torMan), 'ntick-is-handled');

        // ticket has been handled
        unset($ticket);
        $ticket = $ticketManager->findById($this->torrent->id());
        $this->assertTrue($ticket->isDone(), 'ntick-is-done');
    }

    public function testProcessBacklog(): void {
        $this->userList = [
            'backlog' => Helper::makeUser('backlog.' . randomString(10), 'notification-ticket'),
        ];
        $filter = (new Gazelle\Notification\Filter)
            ->setLabel('Backlog')
            ->setMultiLine('artist', '') // TODO: INSERT fails on not null assertion
            ->setMultiLine('record.label', "Unitest Artists Corporation")
            ->create($this->userList['backlog']->id());

        $ticketManager = new Gazelle\Manager\NotificationTicket;
        $ticket = $ticketManager->create($this->torrent);
        Gazelle\DB::DB()->prepared_query("
            INSERT INTO xbt_files_users
                   (fid, uid, useragent, peer_id, active, remaining, ip, timespent, mtime)
            VALUES (?,   ?,   ?,         ?,       1, 0, '127.0.0.1', 1, unix_timestamp(now() - interval 5 second))
            ",  $this->torrent->id(), $this->torrent->uploaderId(), 'ua-' . randomString(12), randomString(20)
        );

        // look at the pending stats
        $manager = new Gazelle\Manager\Notification;
        $pending = $manager->ticketPendingStats();
        $lastHour = end($pending);
        $this->assertEquals(1, $lastHour['total'], 'notif-stats-pending');

        // process the backlog
        $this->assertEquals(1, $manager->processBacklog($ticketManager, $this->torMan), 'notif-process-backlog');

        // ticket has been handled
        unset($ticket);
        $ticket = $ticketManager->findById($this->torrent->id());
        $this->assertTrue($ticket->isDone(), 'backlog-is-done');

        $rss  = (new Gazelle\Feed)->byFeedName($this->userList['backlog'], 'torrents_music');
        $link = SITE_URL . "/torrents.php?id={$this->torrent->groupId()}&amp;torrentid={$this->torrent->id()}&amp;action=download&amp;torrent_pass={$this->userList['backlog']->announceKey()}";
        $tags = implode(',', $this->torrent->group()->tagNameList());
        $this->assertStringContainsString("<guid>$link</guid>", $rss, 'notif-rss-guid');
        $this->assertStringContainsString("<category><![CDATA[{$tags}]]></category>", $rss, 'notif-rss-tags');
        $this->assertStringContainsString("<dc:creator>{$this->torrent->uploader()->username()}</dc:creator>", $rss, 'notif-rss-user');
    }

    public function testStale(): void {
        $this->userList = [
            'backlog' => Helper::makeUser('backlog.' . randomString(10), 'notification-ticket'),
        ];
        $filter = (new Gazelle\Notification\Filter)
            ->setLabel('Stale')
            ->setMultiLine('artist', '') // TODO: INSERT fails on not null assertion
            ->setMultiLine('record.label', "Unitest Artists Corporation")
            ->create($this->userList['backlog']->id());

        $ticketManager = new Gazelle\Manager\NotificationTicket;
        $ticket        = $ticketManager->create($this->torrent);
        $manager       = new Gazelle\Manager\Notification;
        foreach (range(1, 59) as $try) {
            $manager->processBacklog($ticketManager, $this->torMan);
        }

        unset($ticket);
        $ticket = $ticketManager->findById($this->torrent->id());
        $this->assertTrue($ticket->isPending(), 'ntick-stale-is-pending');

        $manager->processBacklog($ticketManager, $this->torMan);
        unset($ticket);
        $ticket = $ticketManager->findById($this->torrent->id());
        $this->assertTrue($ticket->isStale(), 'ntick-stale-is-stale');
    }

    public function testNewGroup(): void {
        $this->userList = [
            'new.grp' => Helper::makeUser('new.grp.' . randomString(10), 'notification-ticket'),
        ];
        $filter = (new Gazelle\Notification\Filter)
            ->setLabel('New Group')
            ->setMultiLine('artist', '') // TODO: INSERT fails on not null assertion
            ->setBoolean('new_groups_only', true)
            ->create($this->userList['new.grp']->id());

        $ticketManager = new Gazelle\Manager\NotificationTicket;
        $ticket        = $ticketManager->create($this->torrent);
        Gazelle\DB::DB()->prepared_query("
            INSERT INTO xbt_files_users
                   (fid, uid, useragent, peer_id, active, remaining, ip, timespent, mtime)
            VALUES (?,   ?,   ?,         ?,       1, 0, '127.0.0.1', 1, unix_timestamp(now() - interval 5 second))
            ",  $this->torrent->id(), $this->torrent->uploaderId(), 'ua-' . randomString(12), randomString(20)
        );

        // handle the ticket
        $manager = new Gazelle\Manager\Notification;
        $manager->handleTicket($ticket, $this->torMan);

        $notifier = new Gazelle\Notification\Torrent($this->userList['new.grp']->id());
        $this->assertEquals(1, $notifier->total(), 'notifier-new-group-1-total');
        $this->assertEquals(1, $notifier->catchupFilter($filter), 'notifier-new-group-catchup');
        $this->assertEquals(1, $notifier->clearFilter($filter), 'notifier-new-group-clear');
        $this->assertEquals(0, $notifier->total(), 'notifier-new-group-1-cleared-total');

        $newTorrent = $this->torMan->create(
            tgroup:                  $this->torrent->group(),
            user:                    $this->torrent->uploader(),
            description:             'notify release second description',
            media:                   'WEB',
            format:                  'FLAC',
            encoding:                '24bit Lossless',
            infohash:                'infohash-' . randomString(10),
            filePath:                'unit-test',
            fileList:                [],
            size:                    123_456_789,
            isScene:                 false,
            isRemaster:              true,
            remasterYear:            2023,
            remasterTitle:           '',
            remasterRecordLabel:     'Unitest Artists',
            remasterCatalogueNumber: 'UA-NOTIF-1X',
        );

        $ticket = $ticketManager->create($newTorrent);
        Gazelle\DB::DB()->prepared_query("
            INSERT INTO xbt_files_users
                   (fid, uid, useragent, peer_id, active, remaining, ip, timespent, mtime)
            VALUES (?,   ?,   ?,         ?,       1, 0, '127.0.0.1', 1, unix_timestamp(now() - interval 5 second))
            ",  $newTorrent->id(), $newTorrent->uploaderId(), 'ua-' . randomString(12), randomString(20)
        );
        $manager->handleTicket($ticket, $this->torMan);

        $notifier = new Gazelle\Notification\Torrent($this->userList['new.grp']->id());
        $this->assertEquals(0, $notifier->total(), 'notifier-no-new-group-2-total');

        $stats = $manager->ticketStats();
        $this->assertEquals(0, $stats['active']['total'], 'notifier-ticket-stats-active');
        $this->assertEquals(0, $stats['pending']['total'], 'notifier-ticket-stats-pending');
        $this->assertEquals(0, $stats['removed']['total'], 'notifier-ticket-stats-removed');
        $this->assertEquals(0, $stats['stale']['total'], 'notifier-ticket-stats-stale');

        $newTorrent->remove($this->torrent->uploader(), 'notify second unit test');
    }
}
