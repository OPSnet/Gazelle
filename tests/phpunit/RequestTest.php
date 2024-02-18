<?php

use PHPUnit\Framework\TestCase;

require_once(__DIR__ . '/../../lib/bootstrap.php');
require_once(__DIR__ . '/../helper.php');

class RequestTest extends TestCase {
    protected Gazelle\Request $request;
    protected Gazelle\TGroup  $tgroup;
    protected array           $userList;

    public function setUp(): void {
        // we need two users, one who uploads and one who snatches
        $this->userList = [
            'admin' => Helper::makeUser('req.' . randomString(10), 'request'),
            'user'  => Helper::makeUser('req.' . randomString(10), 'request'),
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
            Helper::removeTGroup($this->tgroup, $this->userList['admin']);
            foreach ($this->userList as $user) {
                $user->remove();
            }
        }
    }

    public function testCreate(): void {
        $admin = $this->userList['admin'];
        $user  = $this->userList['user'];

        $manager = new Gazelle\Manager\Request();
        $title   = 'phpunit ' . randomString(6) . ' Test Sessions';
        $image   = 'https://example.com/req.jpg';
        $this->request = $manager->create(
            user:            $admin,
            bounty:          1024 ** 2 * REQUEST_MIN,
            categoryId:      (new Gazelle\Manager\Category())->findIdByName('Music'),
            year:            2018,
            title:           $title,
            image:           $image,
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
        $id = $this->request->id();

        $this->assertInstanceOf(Gazelle\Request::class, $this->request, 'request-create');
        $artistName = 'phpunit req ' . randomString(6);
        $this->assertEquals(
            1,
            $this->request->artistRole()->set(
                [ARTIST_MAIN => [$artistName]],
                new Gazelle\Manager\Artist()
            ),
            'request-add-artist-role'
        );
        $this->assertInstanceOf(Gazelle\ArtistRole\Request::class, $this->request->artistRole(), 'request-artist-role');
        $this->assertInstanceOf(Gazelle\Request\LogCue::class, $this->request->logCue(), 'request-log-cue');

        $this->assertCount(0, $this->request->tagNameList());
        $tagMan = new Gazelle\Manager\Tag();
        $tagId  = $tagMan->create('jazz', $admin);
        $this->assertGreaterThan(0, $tagId, 'request-create-tag');
        $this->assertEquals(1, $this->request->addTag($tagId), 'request-add-tag');
        $this->assertEquals(
            1,
            $this->request->addTag($tagMan->create('vapor.wave', $admin)),
            'request-add-second-tag',
        );

        // Request::info() will now succeed
        $this->assertEquals($admin->id(), $this->request->userId(), 'request-user-id');
        $this->assertEquals($admin->id(), $this->request->ajaxInfo()['requestorId'], 'request-ajax-user-id');
        $this->assertEquals("$artistName – $title [2018]", $this->request->text(), 'request-text');

        $find = $manager->findByArtist((new Gazelle\Manager\Artist())->findByName($artistName));
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

        $this->assertTrue(Helper::recentDate($this->request->created()), 'request-created');
        $this->assertEquals($image, $this->request->image(), 'request-image');
        $this->assertEquals($title, $this->request->title(), 'request-title');
        $this->assertEquals('Album', $this->request->releaseTypeName(), 'request-release-type-name');
        $this->assertEquals(2018, $this->request->year(), 'request-year');
        $this->assertNull($this->request->tgroupId(), 'request-no-tgroup');
        $this->assertEquals('UA-7890', $this->request->catalogueNumber(), 'request-can-no');
        $this->assertEquals('Unitest Artists', $this->request->recordLabel(), 'request-rec-label');
        $this->assertEquals(1, $this->request->categoryId(), 'request-cat-id');
        $this->assertEquals('Music', $this->request->categoryName(), 'request-cat-name');
        $this->assertTrue($this->request->hasArtistRole(), 'request-has-artist-role');
        $this->assertInstanceOf(\Gazelle\ArtistRole\Request::class, $this->request->artistRole(), 'request-artist-role');
        $this->assertEquals(['jazz', 'vapor.wave'], $this->request->flush()->tagNameList(), 'request-tag-list');
        $this->assertEquals('jazz vapor_wave', $this->request->tagNameToSphinx(), 'request-tag-sphinx');
        $this->assertEquals(
            '<a href="requests.php?tags=jazz">jazz</a> <a href="requests.php?tags=vapor.wave">vapor.wave</a>',
            $this->request->tagSearchLink(), 'request-tag-searchlink'
        );
        $this->assertFalse($this->request->isFilled(), 'request-not-filled');

        $this->assertEquals(
            3,
            $this->request->setTagList(['acoustic', 'electronic', 'electronic', 'metal'], $user, new Gazelle\Manager\Tag()),
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
        $statsReq = new Gazelle\Stats\Request();
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

        $requestMan = new Gazelle\Manager\Request();
        $title  = 'phpunit req fill ' . randomString(6);
        $bounty = 1024 ** 2 * REQUEST_MIN;
        $this->request = $requestMan->create(
            user:            $admin,
            bounty:          $bounty,
            categoryId:      (new Gazelle\Manager\Category())->findIdByName('Music'),
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

        $statsUser = new Gazelle\Stats\Users();
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

        // add some bounty
        $this->assertTrue($this->request->vote($user, $bounty), 'request-more-bounty');
        $userVote = $this->request->userVote($user);
        $this->assertEquals($user->id(), $userVote['user_id'], 'request-user-vote-user-id');
        $this->assertEquals($taxedBounty, $userVote['bounty'], 'request-user-vote-bounty');
        $this->assertEquals(2, $this->request->userVotedTotal(), 'request-total-voted');
        $this->assertEquals(2 * $taxedBounty, $this->request->bountyTotal(), 'request-total-bounty-added');
        $this->assertTrue(Helper::recentDate($this->request->lastVoteDate()), 'request-last-vote-date');
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
        $this->tgroup = Helper::makeTGroupMusic(
            name:       $tgroupName,
            artistName: [[ARTIST_MAIN], ['Request Girl ' . randomString(12)]],
            tagName:    ['electronic'],
            user:       $user,
        );
        Helper::makeTorrentMusic(
            tgroup: $this->tgroup,
            user:   $user,
            title:  'Deluxe Edition',
        );
        $torrentId = current($this->tgroup->torrentIdList());
        $torrent = (new Gazelle\Manager\Torrent())->findById($torrentId);
        $this->assertInstanceOf(Gazelle\Torrent::class, $torrent, 'request-torrent-filler');

        $this->assertCount(0, $this->request->validate($torrent), 'request-validate');
        $this->assertEquals(1, $this->request->fill($user, $torrent), 'request-fill');
        $this->assertEquals($fillBefore['bounty-size'] + $taxedBounty * 2, $user->stats()->requestBountySize(), 'request-fill-receive-bounty');
        $this->assertEquals($fillBefore['bounty-total'] + 1, $user->stats()->requestBountyTotal(), 'request-fill-receive-total');
        $this->assertTrue(Helper::recentDate($this->request->fillDate()), 'request-fill-date');

        $statsReq->flush();
        $this->assertEquals($before['total'] + 1, $statsReq->total(), 'request-stats-now-total');
        $this->assertEquals($before['total-filled'] + 1, $statsReq->filledTotal(), 'request-stats-now-filled');
        $this->assertIsFloat($statsReq->filledPercent(), 'request-stats-filled-percent');
        $this->assertTrue($this->request->isFilled(), 'request-now-filled');

        // and now unfill it
        $this->assertEquals(1, $this->request->unfill($this->userList['admin'], 'unfill unittest', new Gazelle\Manager\Torrent()), 'request-unfill');
        $this->assertEquals($fillBefore['uploaded'], $this->userList['user']->flush()->uploadedSize(), 'request-fill-unfill-user');
        $this->assertEquals($fillBefore['bounty-total'], $this->userList['user']->stats()->requestBountyTotal(), 'request-fill-unfill-total');
        $this->assertFalse($this->request->isFilled(), 'request-unfilled');

        $log = new Gazelle\Manager\SiteLog(new Gazelle\Manager\User());
        $page = $log->page(1, 0, $this->request->title(), bypassSphinx: true);
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
        $manager = new Gazelle\Manager\Request();
        $this->request = $manager->create(
            user:            $this->userList['admin'],
            bounty:          1024 ** 2 * REQUEST_MIN,
            categoryId:      (new Gazelle\Manager\Category())->findIdByName('Music'),
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
            new Gazelle\Manager\Artist(),
        );
        $this->request->addTag((new Gazelle\Manager\Tag())->create('classical.era', $this->userList['admin']));
        $this->assertTrue(
            (new Gazelle\User\Bookmark($this->userList['user']))->create('request', $this->request->id()),
            'request-bookmark-add'
        );
        $this->assertEquals(1, $this->request->updateBookmarkStats(), 'request-bookmark-update');
        $find = $manager->findUnfilledByUser($this->userList['admin'], 2);
        $this->assertCount(1, $find, 'request-find-unfilled');
        $this->assertEquals($this->request->id(), $find[0]->id(), 'request-found');
    }

    public function testReport(): void {
        $manager = new Gazelle\Manager\Request();
        $this->request = $manager->create(
            user:            $this->userList['admin'],
            bounty:          1024 ** 2 * REQUEST_MIN,
            categoryId:      (new Gazelle\Manager\Category())->findIdByName('Music'),
            year:            (int)date('Y'),
            title:           'phpunit request report',
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
            new Gazelle\Manager\Artist(),
        );
        $this->request->addTag((new Gazelle\Manager\Tag())->create('funk', $this->userList['admin']));

        $title = 'phpunit request report';
        $report = (new Gazelle\Manager\Report(new Gazelle\Manager\User()))->create(
            $this->userList['user'], $this->request->id(), 'request', $title
        );
        $this->assertEquals('phpunit request report', $report->reason(), 'request-report-reason');
        $requestReport = new Gazelle\Report\Request($report->id(), $this->request);
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
        $this->request = (new Gazelle\Manager\Request())->create(
            user:            $this->userList['admin'],
            bounty:          1024 ** 2 * REQUEST_MIN,
            categoryId:      (new Gazelle\Manager\Category())->findIdByName('Music'),
            year:            (int)date('Y'),
            title:           'phpunit request json',
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
            new Gazelle\Manager\Artist(),
        );

        $this->request->addTag((new Gazelle\Manager\Tag())->create('jazz', $this->userList['admin']));
        $this->assertInstanceOf(Gazelle\Request::class, $this->request, 'request-json-create');

        $json = new Gazelle\Json\Request(
            $this->request,
            $this->userList['user'],
            new Gazelle\User\Bookmark($this->userList['user']),
            new Gazelle\Comment\Request($this->request->id(), 1, 0),
            new Gazelle\Manager\User(),
        );
        $payload = $json->payload();
        $this->assertCount(39, $payload, 'req-json-payload');
        $this->assertTrue($payload['canVote'], 'req-json-can-vote');
        $this->assertFalse($payload['canEdit'], 'req-json-can-edit');
        $this->assertEquals($payload['timeAdded'], $payload['lastVote'], 'req-json-date');
        $this->assertEquals('', $payload['fillerName'], 'req-json-can-vote');
        $this->assertEquals('UA-7890', $payload['catalogueNumber'], 'req-json-catno');

        $encoding = $this->request->encoding();
        $this->assertTrue($encoding->isValid(), 'req-enc-valid');
        $this->assertFalse($encoding->all(), 'req-enc-all');
        $this->assertFalse($encoding->exists('320'), 'req-enc-no-enc');
        $this->assertTrue($encoding->exists('Lossless'), 'req-enc-enc');

        $format = $this->request->format();
        $this->assertTrue($format->isValid(), 'req-for-valid');
        $this->assertFalse($format->all(), 'req-for-all');
        $this->assertFalse($format->exists('MP3'), 'req-for-no-for');
        $this->assertTrue($format->exists('FLAC'), 'req-for-for');

        $media = $this->request->media();
        $this->assertTrue($media->isValid(), 'req-med-valid');
        $this->assertFalse($media->all(), 'req-med-all');
        $this->assertFalse($media->exists('Vinyl'), 'req-med-no-med');
        $this->assertTrue($media->exists('WEB'), 'req-med-med');
    }

    public function testEncodingValue(): void {
        $allEncoding = new Gazelle\Request\Encoding();
        $this->assertFalse($allEncoding->isValid(), 'req-enc-all-invalid');
        $this->assertFalse($allEncoding->exists('Lossless'), 'req-enc-invalid-exists');

        $allEncoding = new Gazelle\Request\Encoding(true);
        $this->assertTrue($allEncoding->isValid(), 'req-enc-all-valid');
        $this->assertTrue($allEncoding->exists('Lossless'), 'req-enc-all-flac');
        $this->assertTrue($allEncoding->exists('Morse'), 'req-enc-all-morse'); // because of all encodings shortcut
        $this->assertEquals('Any', $allEncoding->dbValue(), 'req-enc-all-value');

        $some = new Gazelle\Request\Encoding(false, [1, 2]); // 24bit Lossless, V0 (VBR)
        $this->assertTrue($some->isValid(), 'req-enc-some-valid');
        $this->assertTrue($some->exists('24bit Lossless'), 'req-enc-some-lossless');
        $this->assertFalse($some->exists('Morse'), 'req-enc-some-morse');
        $this->assertEquals("24bit Lossless|V0 (VBR)", $some->dbValue(), 'req-enc-some-value');
    }

    public function testFormatValue(): void {
        $allFormat = new Gazelle\Request\Format();
        $this->assertFalse($allFormat->isValid(), 'req-fmt-all-invalid');
        $this->assertFalse($allFormat->exists('MP3'), 'req-fmt-invalid-exists');

        $allFormat = new Gazelle\Request\Format(true);
        $this->assertTrue($allFormat->isValid(), 'req-fmt-all-valid');
        $this->assertTrue($allFormat->exists('FLAC'), 'req-fmt-all-flac');
        $this->assertEquals('Any', $allFormat->dbValue(), 'req-fmt-all-value');

        $some = new Gazelle\Request\Format(false, [0, 1]); // FLAC, MP3
        $this->assertTrue($some->isValid(), 'req-fmt-some-valid');
        $this->assertTrue($some->exists('FLAC'), 'req-fmt-some-flac');
        $this->assertEquals("MP3|FLAC", $some->dbValue(), 'req-fmt-some-value');

        $also = new Gazelle\Request\Format(false, [1, 0]);
        $this->assertEquals("MP3|FLAC", $also->dbValue(), 'req-fmt-same-value');
    }

    public function testMediaValue(): void {
        $allMedia = new Gazelle\Request\Media();
        $this->assertFalse($allMedia->isValid(), 'req-med-all-invalid');
        $this->assertFalse($allMedia->exists('BD'), 'req-med-invalid-exists');

        $allMedia = new Gazelle\Request\Media(true);
        $this->assertTrue($allMedia->isValid(), 'req-med-all-valid');
        $this->assertTrue($allMedia->exists('CD'), 'req-med-all-cd');
        $this->assertEquals('Any', $allMedia->dbValue(), 'req-med-all-value');

        $some = new Gazelle\Request\Media(false, [8, 0, 1, 2]); // Cassette, CD, WEB, Vinyl
        $this->assertTrue($some->isValid(), 'req-med-some-valid');
        $this->assertTrue($some->exists('Vinyl'), 'req-med-some-vinyl');
        $this->assertEquals("CD|WEB|Vinyl|Cassette", $some->dbValue(), 'req-med-some-value');
    }

    public function testLogCueValue(): void {
        $none = new Gazelle\Request\LogCue();
        $this->assertTrue($none->isValid(), 'req-none-valid');
        $this->assertEquals(0, $none->minScore(), 'req-none-min-score');
        $this->assertFalse($none->needLogChecksum(), 'req-none-need-checksum');
        $this->assertFalse($none->needCue(), 'req-none-need-cue');
        $this->assertFalse($none->needLog(), 'req-none-need-log');
        $this->assertEquals('', $none->dbValue(), 'req-none-value');

        $cksum = new Gazelle\Request\LogCue(needLogChecksum: true);
        $this->assertTrue($cksum->isValid(), 'req-cksum-valid');
        $this->assertTrue($cksum->needLogChecksum(), 'req-cksum-need');

        $cue = new Gazelle\Request\LogCue(needCue: true);
        $this->assertTrue($cue->isValid(), 'req-cue-valid');
        $this->assertEquals('Cue', $cue->dbValue(), 'req-cue-value');

        $log = new Gazelle\Request\LogCue(needLog: true);
        $this->assertTrue($log->isValid(), 'req-log-valid');
        $this->assertEquals('Log', $log->dbValue(), 'req-log-value');

        $logcue = new Gazelle\Request\LogCue(needCue: true, needLog: true);
        $this->assertTrue($logcue->isValid(), 'req-log-cue-valid');
        $this->assertEquals('Log + Cue', $logcue->dbValue(), 'req-log-cue-value');

        $logmin = new Gazelle\Request\LogCue(needCue: true, needLog: true, minScore: 50);
        $this->assertTrue($logmin->isValid(), 'req-log-min-valid');
        $this->assertEquals(50, $logmin->minScore(), 'req-log-min-min-score');
        $this->assertTrue($logmin->needCue(), 'req-log-min-need-cue');
        $this->assertTrue($logmin->needLog(), 'req-log-min-need-log');
        $this->assertEquals('Log (>= 50%) + Cue', $logmin->dbValue(), 'req-log-min-value');

        $logmax = new Gazelle\Request\LogCue(needCue: true, needLog: true, minScore: 100);
        $this->assertTrue($logmax->isValid(), 'req-log-max-valid');
        $this->assertEquals('Log (100%) + Cue', $logmax->dbValue(), 'req-log-max-value');

        $over = new Gazelle\Request\LogCue(needCue: true, needLog: true, minScore: 101);
        $this->assertFalse($over->isValid(), 'req-log-score-over');

        $under = new Gazelle\Request\LogCue(needCue: true, needLog: true, minScore: -1);
        $this->assertFalse($under->isValid(), 'req-log-score-under');
    }
}
