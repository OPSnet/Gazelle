<?php

namespace Gazelle;

use PHPUnit\Framework\TestCase;

define('BUFFER_FOR_BOUNTY', 1024 ** 4); // some upload credit to allow request creation

class RequestTest extends TestCase {
    protected Request $request;
    protected TGroup  $tgroup;
    protected array           $userList;

    public function setUp(): void {
        // we need two users, one who uploads and one who snatches
        $this->userList = [
            'admin' => \GazelleUnitTest\Helper::makeUser('req.' . randomString(10), 'request'),
            'user'  => \GazelleUnitTest\Helper::makeUser('req.' . randomString(10), 'request'),
        ];
        $this->userList['admin']->setField('Enabled', '1')->setField('PermissionID', SYSOP)->modify();
        $this->userList['user']->setField('Enabled', '1')->modify();
    }

    public function tearDown(): void {
        if (isset($this->request)) {
            // testCreate() removes it for an assertion
            $this->request->remove();
        }
        if (isset($this->tgroup)) {
            \GazelleUnitTest\Helper::removeTGroup($this->tgroup, $this->userList['admin']);
            foreach ($this->userList as $user) {
                $user->remove();
            }
        }
    }

    public function testCreate(): void {
        $admin = $this->userList['admin'];
        $user  = $this->userList['user'];

        $admin->addBounty(BUFFER_FOR_BOUNTY);
        $manager       = new Manager\Request();
        $title         = 'phpunit ' . randomString(6) . ' Test Sessions (bonus VIP)';
        $image         = 'https://example.com/req.jpg';
        $this->request = \GazelleUnitTest\Helper::makeRequestMusic($admin, $title, image: $image);
        $id = $this->request->id();

        $this->assertInstanceOf(Request::class, $this->request, 'request-create');
        $this->assertStringNotContainsString(' (bonus VIP)', $this->request->urlencodeTitle(), 'request-urlencode-title');
        $artistMan  = new Manager\Artist();
        $artistName = 'phpunit req ' . randomString(6);
        $this->assertEquals(
            1,
            $this->request->artistRole()->set(
                [ARTIST_MAIN => [$artistName]],
                $user,
                $artistMan,
            ),
            'request-add-artist-role'
        );
        $this->assertInstanceOf(ArtistRole\Request::class, $this->request->artistRole(), 'request-artist-role');
        $this->assertInstanceOf(Request\LogCue::class, $this->request->logCue(), 'request-log-cue');
        $this->assertStringContainsString('+', $this->request->urlencodeArtist(), 'request-urlencode-artist');

        $this->assertCount(0, $this->request->tagNameList());
        $nameList = ['phpunit.' . randomString(6), 'phpunit.' . randomString(6)];
        $tagMan   = new Manager\Tag();
        $tag      = $tagMan->create($nameList[0], $admin);
        $this->assertEquals(1, $tag->addRequest($this->request), 'request-add-tag');
        $this->assertEquals(
            1,
            $tagMan->create($nameList[1], $admin)->addRequest($this->request),
            'request-add-second-tag',
        );

        // Request::info() will now succeed
        $this->assertEquals($admin->id(), $this->request->userId(), 'request-user-id');
        $this->assertEquals($admin->id(), $this->request->ajaxInfo()['requestorId'], 'request-ajax-user-id');
        $year = date('Y');
        $this->assertEquals("$artistName – $title [$year]", $this->request->text(), 'request-text');
        $find = $manager->findByArtist($artistMan->findByName($artistName));
        $this->assertCount(1, $find, 'request-find-by-artist');
        $this->assertEquals($id, $find[0]->id(), 'request-find-id');

        $this->assertEquals(1, $this->request->releaseType(), 'request-release-type-id');
        $location = "requests.php?action=view&id=$id";
        $this->assertEquals($location, $this->request->location(), 'request-location');
        $this->assertEquals(SITE_URL . "/$location", $this->request->publicLocation(), 'request-public-location');
        $this->assertEquals(htmlentities($location), $this->request->url(), 'request-url');
        $this->assertEquals(SITE_URL . '/' . htmlentities($location), $this->request->publicUrl(), 'request-public-url');
        $this->assertEquals(
            "<a href=\"requests.php?action=view&amp;id={$id}\">{$title}</a>",
            $this->request->link(),
            'request-link'
        );
        $artistId = $this->request->artistRole()->idList()[ARTIST_MAIN][0]['id'];
        $artistName = $this->request->artistRole()->idList()[ARTIST_MAIN][0]['name'];
        $this->assertEquals(
            "<a href=\"artist.php?id=$artistId\" dir=\"ltr\">$artistName</a> – {$this->request->title()} [{$this->request->year()}]",
            $this->request->selfLink(),
            'request-self-link'
        );
        $this->assertEquals(
            "<a href=\"artist.php?id=$artistId\" dir=\"ltr\">$artistName</a> – {$this->request->link()} [{$this->request->year()}]",
            $this->request->smartLink(),
            'request-smart-link'
        );

        $this->assertTrue(\GazelleUnitTest\Helper::recentDate($this->request->created()), 'request-created');
        $this->assertEquals($image, $this->request->image(), 'request-image');
        $this->assertEquals($title, $this->request->title(), 'request-title');
        $this->assertEquals('Album', $this->request->releaseTypeName(), 'request-release-type-name');
        $this->assertEquals($year, $this->request->year(), 'request-year');
        $this->assertNull($this->request->tgroupId(), 'request-no-tgroup');
        $this->assertEquals('UA-7890', $this->request->catalogueNumber(), 'request-can-no');
        $this->assertEquals('Unitest Artists', $this->request->recordLabel(), 'request-rec-label');
        $this->assertEquals(1, $this->request->categoryId(), 'request-cat-id');
        $this->assertEquals('Music', $this->request->categoryName(), 'request-cat-name');
        $this->assertTrue($this->request->hasArtistRole(), 'request-has-artist-role');
        $this->assertInstanceOf(ArtistRole\Request::class, $this->request->artistRole(), 'request-artist-role');
        $this->assertEquals($nameList, $this->request->flush()->tagNameList(), 'request-tag-list');
        $this->assertEquals(str_replace('.', '_', "{$nameList[0]} {$nameList[1]}"), $this->request->tagNameToSphinx(), 'request-tag-sphinx');
        $this->assertEquals(
            "<a href=\"requests.php?tags={$nameList[0]}\">{$nameList[0]}</a> <a href=\"requests.php?tags={$nameList[1]}\">{$nameList[1]}</a>",
            $this->request->tagLinkList(), 'request-tag-linklist'
        );
        $this->assertFalse($this->request->isFilled(), 'request-not-filled');

        $more = ['phpunit.' . randomString(6), 'phpunit.' . randomString(6), 'phpunit.' . randomString(6)];
        $this->assertEquals(
            3,
            $tagMan->replaceTagList(
                $this->request,
                [$more[0], $more[1], $more[1], $more[2]],
                $user,
            ),
            'request-set-tag'
        );

        $this->assertEquals('123,456', $this->request->oclc(), 'request-oclc');
        $this->assertEquals(
            '<a href="https://www.worldcat.org/oclc/123">123</a>, <a href="https://www.worldcat.org/oclc/456">456</a>',
            $this->request->oclcLink(),
            'request-oclc-link'
        );
        $this->assertEquals('This is a unit test description', $this->request->description(), 'request-description');
        $this->assertTrue($this->request->needCue(), 'request-need-cue');
        $this->assertTrue($this->request->needLog(), 'request-need-log');
        $this->assertTrue($this->request->needLogChecksum(), 'request-need-checksum');
        $this->assertEquals(100, $this->request->needLogScore(), 'request-log-score');
        $this->assertEquals('Log (100%) + Cue', $this->request->descriptionLogCue(), 'request-descn-log-cue');
        $this->assertEquals('1', $this->request->legacyLogChecksum(), 'request-legacy-checksum');

        $this->assertEquals(['Lossless', 'V0 (VBR)'], array_values($this->request->currentEncoding()), 'request-cur-encoding');
        $this->assertEquals(['Lossless', 'V0 (VBR)'], array_values($this->request->needEncodingList()), 'request-need-encoding');
        $this->assertEquals('Lossless, V0 (VBR)', $this->request->descriptionEncoding(), 'request-descr-encoding');
        $this->assertEquals('Lossless|V0 (VBR)', $this->request->legacyEncodingList(), 'request-legacy-encoding');
        $this->assertTrue($this->request->needEncoding('Lossless'), 'request-need-encoding-lossless');
        $this->assertFalse($this->request->needEncoding('24bit Lossless'), 'request-need-encoding-24bit');
        $this->assertNotEquals(ENCODING, $this->request->currentEncoding(), 'request-encoding-specified');

        $this->assertEquals(['MP3', 'FLAC'], $this->request->currentFormat(), 'request-cur-format');
        $this->assertEquals(['MP3', 'FLAC'], $this->request->needFormatList(), 'request-list-format');
        $this->assertEquals('MP3, FLAC', $this->request->descriptionFormat(), 'request-descr-format');
        $this->assertEquals('MP3|FLAC', $this->request->legacyFormatList(), 'request-legacy-format');
        $this->assertTrue($this->request->needFormat('FLAC'), 'request-need-format-flac');
        $this->assertFalse($this->request->needFormat('AAC'), 'request-need-format-aac');
        $this->assertNotEquals(FORMAT, $this->request->currentFormat(), 'request-format-specified');

        $this->assertEquals(['CD', 'WEB'], $this->request->currentMedia(), 'request-cur-media');
        $this->assertEquals(['CD', 'WEB'], $this->request->needMediaList(), 'request-list-media');
        $this->assertEquals('CD, WEB', $this->request->descriptionMedia(), 'request-descr-media');
        $this->assertEquals('CD|WEB', $this->request->legacyMediaList(), 'request-legacy-media');
        $this->assertTrue($this->request->needMedia('CD'), 'request-need-media-cd');
        $this->assertFalse($this->request->needMedia('DVD'), 'request-need-media-DVD');
        $this->assertNotEquals(MEDIA, $this->request->currentMedia(), 'request-media-specified');

        $this->assertTrue($this->request->canVote($admin), 'request-vote-owner');
        $this->assertTrue($this->request->canVote($user), 'request-vote-other');
        $this->assertTrue($this->request->canEditOwn($admin), 'request-edit-own-owner');
        $this->assertTrue($this->request->canEdit($admin), 'request-edit-owner');
        $this->assertFalse($this->request->canEditOwn($user), 'request-edit-own-other');
        $this->assertFalse($this->request->canEdit($user), 'request-edit-other');

        $this->assertTrue($this->request->remove(), 'request-remove');
        unset($this->request); // success, no need to tidy up
        $this->assertNull($manager->findById($id), 'request-gone');
    }

    public function testFill(): void {
        $statsReq = new Stats\Request();
        $statsReq->flush();
        $admin  = $this->userList['admin'];
        $user   = $this->userList['user'];
        $before = [
            'created-size'  => 0,
            'created-total' => 0,
            'vote-size'     => 0,
            'vote-total'    => 0,
            'uploaded'      => $admin->uploadedSize(),
            'total'         => $statsReq->total(),
            'total-filled'  => $statsReq->filledTotal(),
        ];

        $requestMan = new Manager\Request();
        $title  = 'phpunit req fill ' . randomString(6);
        $bounty = 1024 ** 2 * REQUEST_MIN;
        $this->request = $requestMan->create(
            user:            $admin,
            bounty:          $bounty,
            categoryId:      (new Manager\Category())->findIdByName('Music'),
            year:            2018,
            title:           $title,
            image:           '',
            description:     'This is a unit test description',
            recordLabel:     'Unitest Artists',
            catalogueNumber: 'UA-7890',
            releaseType:     1,
            encodingList:    'Lossless|V0 (VBR)',
            formatList:      'MP3|FLAC',
            mediaList:       'CD|WEB',
            logCue:          'Log (100%) + Cue',
            checksum:        true,
            oclc:            '123,456',
        );

        $statsReq->flush();
        $this->assertEquals($before['total'] + 1, $statsReq->total(), 'request-stats-new-total');
        $this->assertEquals($before['total-filled'], $statsReq->filledTotal(), 'request-stats-new-filled');

        $statsUser = new Stats\Users();
        $statsUser->refresh();

        $admin->flush();
        $after = [
            'created-size'  => $admin->stats()->requestCreatedSize(),
            'created-total' => $admin->stats()->requestCreatedTotal(),
            'vote-size'     => $admin->stats()->requestVoteSize(),
            'vote-total'    => $admin->stats()->requestVoteTotal(),
            'uploaded'      => $admin->uploadedSize(),
        ];

        $this->assertEquals(0, $this->request->fillerId(), 'request-unfilled-filler-id');
        $this->assertEquals(0, $this->request->torrentId(), 'request-unfilled-torrent-id');
        $this->assertNull($this->request->fillDate(), 'request-unfilled-date');

        $taxedBounty = (int)($bounty * (1 - REQUEST_TAX));
        $this->assertEquals(1 + $before['created-total'], $after['created-total'], 'request-created-total');
        $this->assertEquals(1 + $before['vote-total'], $after['vote-total'], 'request-vote-total');
        $this->assertEquals($taxedBounty, $after['created-size'] - $before['created-size'], 'request-created-size');
        $this->assertEquals($taxedBounty, $after['vote-size'] - $before['vote-size'], 'request-vote-size');
        $this->assertEquals(-$bounty, $after['uploaded'] - $before['uploaded'], 'request-subtract-bounty');

        // If this next test fails, first try re-running the suite. There is a microsecond
        // race condition between requests.LastPostTime and requests.created that would be
        // difficult to remove without adding a lot of complications to the code.
        $this->assertFalse($this->request->hasNewVote(), 'request-no-new-vote');
        sleep(1); // to ensure lastVoteDate() > created()
        // add some bounty
        $this->assertTrue($this->request->vote($user, $bounty), 'request-more-bounty');
        $this->assertTrue($this->request->hasNewVote(), 'request-has-new-vote');
        $userVote = $this->request->userVote($user);
        $this->assertEquals($user->id(), $userVote['user_id'], 'request-user-vote-user-id');
        $this->assertEquals($taxedBounty, $userVote['bounty'], 'request-user-vote-bounty');
        $this->assertEquals(2, $this->request->userVotedTotal(), 'request-total-voted');
        $this->assertEquals(2 * $taxedBounty, $this->request->bountyTotal(), 'request-total-bounty-added');
        $this->assertTrue(\GazelleUnitTest\Helper::recentDate($this->request->lastVoteDate()), 'request-last-vote-date');
        $this->assertEquals(2, $this->request->userVotedTotal(), 'request-user-voted-total');
        $this->assertEquals(
            [$admin->id(), $user->id()],
            array_column($this->request->userIdVoteList(), 'user_id'),
            'request-user-id-vote-list'
        );
        $this->assertEquals($taxedBounty, $this->request->userBounty($admin), 'request-user-bounty-total');
        $this->assertCount(2, array_column($this->request->bounty(), 'UserID'));
        $this->assertCount(2, array_column($this->request->bounty(), 'Bounty'));

        $fillBefore = [
            'uploaded'     => $user->uploadedSize(),
            'bounty-size'  => $user->stats()->requestBountySize(),
            'bounty-total' => $user->stats()->requestBountyTotal(),
        ];

        // make a torrent to fill the request
        $tgroupName = 'phpunit request ' . randomString(6);
        $this->tgroup = \GazelleUnitTest\Helper::makeTGroupMusic(
            name:       $tgroupName,
            artistName: [[ARTIST_MAIN], ['Request Girl ' . randomString(12)]],
            tagName:    ['electronic'],
            user:       $user,
        );
        \GazelleUnitTest\Helper::makeTorrentMusic(
            tgroup: $this->tgroup,
            user:   $user,
            title:  'Deluxe Edition',
        );
        $torrentId = current($this->tgroup->torrentIdList());
        $torrent = (new Manager\Torrent())->findById($torrentId);
        $this->assertInstanceOf(Torrent::class, $torrent, 'request-torrent-filler');

        $this->assertCount(0, $this->request->validate($torrent), 'request-validate');
        $this->assertEquals(1, $this->request->fill($user, $torrent), 'request-fill');
        $this->assertEquals($fillBefore['bounty-size'] + $taxedBounty * 2, $user->stats()->requestBountySize(), 'request-fill-receive-bounty');
        $this->assertEquals($fillBefore['bounty-total'] + 1, $user->stats()->requestBountyTotal(), 'request-fill-receive-total');
        $this->assertTrue(\GazelleUnitTest\Helper::recentDate($this->request->fillDate()), 'request-fill-date');
        $this->assertEquals($this->request->id(), $torrent->requestFills($requestMan)[0]->id(), 'request-torrent-fills');

        $statsReq->flush();
        $this->assertEquals($before['total'] + 1, $statsReq->total(), 'request-stats-now-total');
        $this->assertEquals($before['total-filled'] + 1, $statsReq->filledTotal(), 'request-stats-now-filled');
        $this->assertIsFloat($statsReq->filledPercent(), 'request-stats-filled-percent');
        $this->assertTrue($this->request->isFilled(), 'request-now-filled');

        // and now unfill it
        $this->assertEquals(1, $this->request->unfill($this->userList['admin'], 'unfill unittest', new Manager\Torrent()), 'request-unfill');
        $this->assertEquals($fillBefore['uploaded'], $this->userList['user']->flush()->uploadedSize(), 'request-fill-unfill-user');
        $this->assertEquals($fillBefore['bounty-total'], $this->userList['user']->stats()->requestBountyTotal(), 'request-fill-unfill-total');
        $this->assertFalse($this->request->isFilled(), 'request-unfilled');

        $log = new Manager\SiteLog(new Manager\User());
        $page = $log->page(2, 0, $this->request->title(), bypassSphinx: true);
        $this->assertStringStartsWith(
            "Request <a href=\"{$this->request->url()}\">{$this->request->id()}</a> ({$this->request->title()})",
            $page[0]['message'],
            'request-log-unfill-title'
        );
        $this->assertStringContainsString(
            "was unfilled by user {$admin->id()} ({$admin->link()}) for the reason",
            $page[0]['message'],
            'request-log-unfill-by'
        );
        $this->assertStringContainsString(
            "was filled by user {$user->id()} ({$user->link()}) with the torrent <a href=\"torrents.php?torrentid={$torrent->id()}\">{$torrent->id()}</a>",
            $page[1]['message'],
            'request-log-fill-by'
        );

        $statsReq->flush();
        $this->assertEquals($before['total-filled'], $statsReq->filledTotal(), 'request-stats-now-unfilled');

        $this->assertEquals(1, $this->request->refundBounty($this->userList['user'], $admin->username()), 'request-bounty-refund');
        $this->assertEquals(1, $this->request->removeBounty($admin, $admin->username()), 'request-bounty-remove');
    }

    public function testBookmark(): void {
        $manager = new Manager\Request();
        $this->request = $manager->create(
            user:            $this->userList['admin'],
            bounty:          1024 ** 2 * REQUEST_MIN,
            categoryId:      (new Manager\Category())->findIdByName('Music'),
            year:            (int)date('Y'),
            title:           'phpunit request bookmark',
            image:           '',
            description:     'This is a unit test description',
            recordLabel:     'Unitest Artists',
            catalogueNumber: 'UA-7890',
            releaseType:     1,
            encodingList:    'Lossless',
            formatList:      'FLAC',
            mediaList:       'WEB',
            checksum:        false,
            logCue:          '',
            oclc:            '',
        );
        $this->request->artistRole()->set(
            [ARTIST_MAIN => ['phpunit req ' . randomString(6)]],
            $this->userList['user'],
            new Manager\Artist(),
        );
        (new Manager\Tag())->create('classical.era', $this->userList['admin'])->addRequest($this->request);
        $this->assertTrue(
            (new User\Bookmark($this->userList['user']))->create('request', $this->request->id()),
            'request-bookmark-add'
        );
        $this->assertEquals(1, $this->request->updateBookmarkStats(), 'request-bookmark-update');
        $find = $manager->findUnfilledByUser($this->userList['admin'], 2);
        $this->assertCount(1, $find, 'request-find-unfilled');
        $this->assertEquals($this->request->id(), $find[0]->id(), 'request-found');
    }

    public function testReport(): void {
        $this->userList['admin']->addBounty(BUFFER_FOR_BOUNTY);
        $this->request = \GazelleUnitTest\Helper::makeRequestMusic($this->userList['admin'], 'phpunit request report');
        $this->request->artistRole()->set(
            [ARTIST_MAIN => ['phpunit req ' . randomString(6)]],
            $this->userList['user'],
            new Manager\Artist(),
        );
        (new Manager\Tag())
            ->create('phpunit.' . randomString(6), $this->userList['admin'])
            ->addRequest($this->request);

        $title = 'phpunit request report';
        $report = (new Manager\Report(new Manager\User()))->create(
            $this->userList['user'], $this->request->id(), 'request', $title
        );
        $this->assertEquals('phpunit request report', $report->reason(), 'request-report-reason');
        $requestReport = new Report\Request($report->id(), $this->request);
        $this->assertStringStartsWith('Request Report: ', $requestReport->title(), 'request-report-title');
        $this->assertEquals('report/request.twig', $requestReport->template(), 'request-report-template');
        $this->assertEquals(
            "the request [url=requests.php?action=view&amp;id={$this->request->id()}]{$title}[/url]",
            $requestReport->bbLink(),
            'request-report-bb-link'
        );
        $report->remove();
    }

    public function testJson(): void {
        $this->userList['admin']->addBounty(BUFFER_FOR_BOUNTY);
        $this->request = \GazelleUnitTest\Helper::makeRequestMusic($this->userList['admin'], 'phpunit request json');
        $artistMan = new Manager\Artist();
        $this->request->artistRole()->set(
            [ARTIST_MAIN => ['phpunit req ' . randomString(6)]],
            $this->userList['user'],
            $artistMan,
        );
        (new Manager\Tag())
            ->create('phpunit.' . randomString(6), $this->userList['admin'])
            ->addRequest($this->request);
        $this->assertInstanceOf(Request::class, $this->request, 'request-json-create');

        $json = new Json\Request(
            $this->request,
            $this->userList['user'],
            new User\Bookmark($this->userList['user']),
            new Comment\Request($this->request->id(), 1, 0),
            new Manager\User(),
        );
        $payload = $json->payload();
        $this->assertCount(39, $payload, 'req-json-payload');
        $this->assertTrue($payload['canVote'], 'req-json-can-vote');
        $this->assertFalse($payload['canEdit'], 'req-json-can-edit');
        // request bounty is added in a separate db operation and the second may roll over
        $this->assertLessThanOrEqual(1, abs(strtotime($payload['timeAdded']) - strtotime($payload['lastVote'])), 'req-json-date');
        $this->assertEquals('', $payload['fillerName'], 'req-json-can-vote');
        $this->assertEquals('UA-7890', $payload['catalogueNumber'], 'req-json-catno');
        $this->assertEquals(['Lossless', 'V0 (VBR)'], $payload['bitrateList'], 'req-json-bitrate-list');
        $this->assertEquals(['MP3', 'FLAC'], $payload['formatList'], 'req-json-format-list');
        $this->assertEquals(['CD', 'WEB'], $payload['mediaList'], 'req-json-media-list');

        $encoding = $this->request->encoding();
        $this->assertTrue($encoding->isValid(), 'req-enc-valid');
        $this->assertFalse($encoding->all(), 'req-enc-all');
        $this->assertFalse($encoding->exists('320'), 'req-enc-no-enc');
        $this->assertTrue($encoding->exists('Lossless'), 'req-enc-enc');

        $format = $this->request->format();
        $this->assertTrue($format->isValid(), 'req-for-valid');
        $this->assertFalse($format->all(), 'req-for-all');
        $this->assertTrue($format->exists('MP3'), 'req-for-no-for');
        $this->assertTrue($format->exists('FLAC'), 'req-for-for');

        $media = $this->request->media();
        $this->assertTrue($media->isValid(), 'req-med-valid');
        $this->assertFalse($media->all(), 'req-med-all');
        $this->assertFalse($media->exists('Vinyl'), 'req-med-no-med');
        $this->assertTrue($media->exists('WEB'), 'req-med-med');
    }

    public function testEncodingValue(): void {
        $allEncoding = new Request\Encoding();
        $this->assertFalse($allEncoding->isValid(), 'req-enc-all-invalid');
        $this->assertFalse($allEncoding->exists('Lossless'), 'req-enc-invalid-exists');

        $allEncoding = new Request\Encoding(true);
        $this->assertTrue($allEncoding->isValid(), 'req-enc-all-valid');
        $this->assertTrue($allEncoding->exists('Lossless'), 'req-enc-all-flac');
        $this->assertTrue($allEncoding->exists('Morse'), 'req-enc-all-morse'); // because of all encodings shortcut
        $this->assertEquals('Any', $allEncoding->dbValue(), 'req-enc-all-value');

        $some = new Request\Encoding(false, [1, 2]); // 24bit Lossless, V0 (VBR)
        $this->assertTrue($some->isValid(), 'req-enc-some-valid');
        $this->assertTrue($some->exists('24bit Lossless'), 'req-enc-some-lossless');
        $this->assertFalse($some->exists('Morse'), 'req-enc-some-morse');
        $this->assertEquals("24bit Lossless|V0 (VBR)", $some->dbValue(), 'req-enc-some-value');
    }

    public function testFormatValue(): void {
        $allFormat = new Request\Format();
        $this->assertFalse($allFormat->isValid(), 'req-fmt-all-invalid');
        $this->assertFalse($allFormat->exists('MP3'), 'req-fmt-invalid-exists');

        $allFormat = new Request\Format(true);
        $this->assertTrue($allFormat->isValid(), 'req-fmt-all-valid');
        $this->assertTrue($allFormat->exists('FLAC'), 'req-fmt-all-flac');
        $this->assertEquals('Any', $allFormat->dbValue(), 'req-fmt-all-value');

        $some = new Request\Format(false, [0, 1]); // FLAC, MP3
        $this->assertTrue($some->isValid(), 'req-fmt-some-valid');
        $this->assertTrue($some->exists('FLAC'), 'req-fmt-some-flac');
        $this->assertEquals("MP3|FLAC", $some->dbValue(), 'req-fmt-some-value');

        $also = new Request\Format(false, [1, 0]);
        $this->assertEquals("MP3|FLAC", $also->dbValue(), 'req-fmt-same-value');
    }

    public function testMediaValue(): void {
        $allMedia = new Request\Media();
        $this->assertFalse($allMedia->isValid(), 'req-med-all-invalid');
        $this->assertFalse($allMedia->exists('BD'), 'req-med-invalid-exists');

        $allMedia = new Request\Media(true);
        $this->assertTrue($allMedia->isValid(), 'req-med-all-valid');
        $this->assertTrue($allMedia->exists('CD'), 'req-med-all-cd');
        $this->assertEquals('Any', $allMedia->dbValue(), 'req-med-all-value');

        $some = new Request\Media(false, [8, 0, 1, 2]); // Cassette, CD, WEB, Vinyl
        $this->assertTrue($some->isValid(), 'req-med-some-valid');
        $this->assertTrue($some->exists('Vinyl'), 'req-med-some-vinyl');
        $this->assertEquals("CD|WEB|Vinyl|Cassette", $some->dbValue(), 'req-med-some-value');
    }

    public function testLogCueValue(): void {
        $none = new Request\LogCue();
        $this->assertTrue($none->isValid(), 'req-none-valid');
        $this->assertEquals(0, $none->minScore(), 'req-none-min-score');
        $this->assertFalse($none->needLogChecksum(), 'req-none-need-checksum');
        $this->assertFalse($none->needCue(), 'req-none-need-cue');
        $this->assertFalse($none->needLog(), 'req-none-need-log');
        $this->assertEquals('', $none->dbValue(), 'req-none-value');

        $cksum = new Request\LogCue(needLogChecksum: true);
        $this->assertTrue($cksum->isValid(), 'req-cksum-valid');
        $this->assertTrue($cksum->needLogChecksum(), 'req-cksum-need');

        $cue = new Request\LogCue(needCue: true);
        $this->assertTrue($cue->isValid(), 'req-cue-valid');
        $this->assertEquals('Cue', $cue->dbValue(), 'req-cue-value');

        $log = new Request\LogCue(needLog: true);
        $this->assertTrue($log->isValid(), 'req-log-valid');
        $this->assertEquals('Log', $log->dbValue(), 'req-log-value');

        $logcue = new Request\LogCue(needCue: true, needLog: true);
        $this->assertTrue($logcue->isValid(), 'req-log-cue-valid');
        $this->assertEquals('Log + Cue', $logcue->dbValue(), 'req-log-cue-value');

        $logmin = new Request\LogCue(needCue: true, needLog: true, minScore: 50);
        $this->assertTrue($logmin->isValid(), 'req-log-min-valid');
        $this->assertEquals(50, $logmin->minScore(), 'req-log-min-min-score');
        $this->assertTrue($logmin->needCue(), 'req-log-min-need-cue');
        $this->assertTrue($logmin->needLog(), 'req-log-min-need-log');
        $this->assertEquals('Log (>= 50%) + Cue', $logmin->dbValue(), 'req-log-min-value');

        $logmax = new Request\LogCue(needCue: true, needLog: true, minScore: 100);
        $this->assertTrue($logmax->isValid(), 'req-log-max-valid');
        $this->assertEquals('Log (100%) + Cue', $logmax->dbValue(), 'req-log-max-value');

        $over = new Request\LogCue(needCue: true, needLog: true, minScore: 101);
        $this->assertFalse($over->isValid(), 'req-log-score-over');

        $under = new Request\LogCue(needCue: true, needLog: true, minScore: -1);
        $this->assertFalse($under->isValid(), 'req-log-score-under');
    }
}
