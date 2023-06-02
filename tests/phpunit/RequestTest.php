<?php

use \PHPUnit\Framework\TestCase;

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
        $this->userList['admin']->setUpdate('Enabled', '1')->setUpdate('PermissionID', SYSOP)->modify();
        $this->userList['user']->setUpdate('Enabled', '1')->modify();

        // create a torrent group
        $tgroupName = 'phpunit request ' . randomString(6);
        $this->tgroup = Helper::makeTGroupMusic(
            name:       $tgroupName,
            artistName: [[ARTIST_MAIN], ['Request Girl ' . randomString(12)]],
            tagName:    ['electronic'],
            user:       $this->userList['user'],
        );

        // add a torrent to the group
        Helper::makeTorrentMusic(
            tgroup: $this->tgroup,
            user:   $this->userList['user'],
            title:  'Deluxe Edition',
        );
    }

    public function tearDown(): void {
        if (isset($this->request)) {
            // testCreate() removes it for an assertion
            $this->request->remove();
        }
        Helper::removeTGroup($this->tgroup, $this->userList['admin']);
        foreach ($this->userList as $user) {
            $user->remove();
        }
    }

    public function testCreate(): void {
        $admin   = $this->userList['admin'];
        $user    = $this->userList['user'];
        $title   = 'The ' . randomString(6). ' Test Sessions';
        $userMan = new Gazelle\Manager\User;

        $statsMan = new Gazelle\Stats\Users;
        $statsMan->refresh();
        $before = [
            'created-size'  => $this->userList['admin']->stats()->requestCreatedSize(),
            'created-total' => $this->userList['admin']->stats()->requestCreatedTotal(),
            'vote-size'     => $this->userList['admin']->stats()->requestVoteSize(),
            'vote-total'    => $this->userList['admin']->stats()->requestVoteTotal(),
            'uploaded'      => $this->userList['admin']->uploadedSize(),
        ];

        $requestMan = new Gazelle\Manager\Request;
        $title = 'The ' . randomString(6). ' Test Sessions';
        $this->request = $requestMan->create(
            userId:          $this->userList['admin']->id(),
            categoryId:      (new Gazelle\Manager\Category)->findIdByName('Music'),
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
            oclc:            '',
        );
        $this->assertInstanceOf(Gazelle\Request::class, $this->request, 'request-create');

        $artistMan  = new Gazelle\Manager\Artist;
        $artistName = 'The ' . randomString(8);
        [$artistId, $aliasId] = $artistMan->create($artistName);
        $this->assertGreaterThan(0, $artistId, 'request-create-artist');
        // FIXME: nuke this horrible legacy code
        $artistMan->setGroupId($this->request->id());
        $artistMan->addToRequest($artistId, $aliasId, ARTIST_MAIN);

        $this->assertCount(0, $this->request->tagNameList());
        $tagMan = new Gazelle\Manager\Tag;
        $tagId  = $tagMan->create('jazz', $this->userList['admin']);
        $this->assertGreaterThan(0, $tagId, 'request-create-tag');
        $this->assertEquals(1, $this->request->addTag($tagId), 'request-add-tag');
        $this->request->addTag($tagMan->create('vaporwave', $this->userList['admin']));

        // FIXME: cannot be asserted earlier: this should not depend on a request having tags
        $this->assertInstanceOf(Gazelle\ArtistRole\Request::class, $this->request->artistRole(), 'request-artist-role');

        // Request::info() will now succeed
        $this->assertEquals($this->userList['admin']->id(), $this->request->userId(), 'request-user-id');
        $this->assertEquals($this->userList['admin']->id(), $this->request->ajaxInfo()['requestorId'], 'request-ajax-user-id');
        $this->assertEquals("$artistName – $title [2018]", $this->request->text(), 'request-text');

        $this->assertEquals(1, $this->request->releaseType(), 'request-release-type-id');
        $location = 'requests.php?action=view&id=' . $this->request->id();
        $this->assertEquals($location, $this->request->location(), 'request-location');
        $this->assertEquals(SITE_URL . "/$location", $this->request->publicLocation(), 'request-public-location');
        $this->assertEquals(htmlentities($location), $this->request->url(), 'request-url');
        $this->assertEquals(SITE_URL . '/' . htmlentities($location), $this->request->publicUrl(), 'request-public-url');
        $this->assertEquals('Album', $this->request->releaseTypeName(), 'request-release-type-name');
        $this->assertEquals(0, $this->request->fillerId(), 'request-unfilled-filler-id');
        $this->assertEquals(0, $this->request->torrentId(), 'request-unfilled-torrent-id');
        $this->assertNull($this->request->fillDate(), 'request-unfilled-date');
        $this->assertNull($this->request->tgroupId(), 'request-no-tgroup');
        $this->assertEquals('UA-7890', $this->request->catalogueNumber(), 'request-can-no');
        $this->assertEquals('Unitest Artists', $this->request->recordLabel(), 'request-rec-label');
        $this->assertEquals(1, $this->request->categoryId(), 'request-cat-id');
        $this->assertEquals('Music', $this->request->categoryName(), 'request-cat-name');
        $this->assertEquals(['jazz', 'vaporwave'], $this->request->tagNameList(), 'request-tag-list');
        $this->assertEquals(
            '<a href="requests.php?tags=jazz">jazz</a> <a href="requests.php?tags=vaporwave">vaporwave</a>',
            $this->request->tagSearchLink(), 'request-tag-searchlink'
        );
        $this->assertEquals('This is a unit test description', $this->request->description(), 'request-description');
        $this->assertTrue($this->request->needCue(), 'request-need-cue');
        $this->assertTrue($this->request->needLog(), 'request-need-log');
        $this->assertTrue($this->request->needLogChecksum(), 'request-need-checksum');
        $this->assertEquals(100, $this->request->needLogScore(), 'request-log-score');
        $this->assertEquals('Log (100%) + Cue', $this->request->descriptionLogCue(), 'request-descn-log-cue');

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

        $this->assertTrue($this->request->canVote($this->userList['admin']), 'request-vote-owner');
        $this->assertTrue($this->request->canVote($this->userList['user']), 'request-vote-other');
        $this->assertTrue($this->request->canEditOwn($this->userList['admin']), 'request-edit-own-owner');
        $this->assertTrue($this->request->canEdit($this->userList['admin']), 'request-edit-owner');
        $this->assertFalse($this->request->canEditOwn($this->userList['user']), 'request-edit-own-other');
        $this->assertFalse($this->request->canEdit($this->userList['user']), 'request-edit-other');

        // Initial vote from creator
        $bounty = 1024**2 * REQUEST_MIN;
        $this->assertTrue($this->request->vote($this->userList['admin'], $bounty), 'request-initial-bounty');

        $statsMan->refresh();
        $this->userList['admin']->flush();
        $after = [
            'created-size'  => $this->userList['admin']->stats()->requestCreatedSize(),
            'created-total' => $this->userList['admin']->stats()->requestCreatedTotal(),
            'vote-size'     => $this->userList['admin']->stats()->requestVoteSize(),
            'vote-total'    => $this->userList['admin']->stats()->requestVoteTotal(),
            'uploaded'      => $this->userList['admin']->uploadedSize(),
        ];

        $taxedBounty = (int)($bounty * (1 - REQUEST_TAX));
        // $this->assertEquals(1 + $before['created-total'], $after['created-total'], 'request-created-total');
        // $this->assertEquals(1 + $before['vote-total'], $after['vote-total'], 'request-vote-total');
        // $this->assertEquals($taxedBounty, $after['created-size'] - $before['created-size'], 'request-created-size');
        // $this->assertEquals($taxedBounty, $after['vote-size'] - $before['vote-size'], 'request-vote-size');
        $this->assertEquals(-$bounty, $after['uploaded'] - $before['uploaded'], 'request-subtract-bounty');

        $this->assertEquals([$this->userList['admin']->id()], array_column($this->request->userIdVoteList(), 'user_id'), 'request-user-id-vote-list');
        $this->assertEquals($taxedBounty, $this->request->userBounty($this->userList['admin']), 'request-user-bounty-total');

        // add some bounty
        $this->assertTrue($this->request->vote($this->userList['user'], $bounty), 'request-more-bounty');
        $this->assertEquals(2, $this->request->userVotedTotal(), 'request-total-voted');
        $this->assertEquals(2 * $taxedBounty, $this->request->bountyTotal(), 'request-total-bounty-added');

        // find a torrent to fill the request
        $fillBefore = [
            'uploaded'     => $this->userList['user']->uploadedSize(),
            'bounty-size'  => $this->userList['user']->stats()->requestBountySize(),
            'bounty-total' => $this->userList['user']->stats()->requestBountyTotal(),
        ];
        $torrentId = (int)Gazelle\DB::DB()->scalar("SELECT min(ID) FROM torrents");
        $torrent = (new Gazelle\Manager\Torrent)->findById($torrentId);
        $this->assertInstanceOf(Gazelle\Torrent::class, $torrent, 'request-torrent-filler');
        $this->assertEquals(1, $this->request->fill($this->userList['user'], $torrent), 'request-fill');
        $this->assertEquals($fillBefore['bounty-size'] + $taxedBounty * 2, $this->userList['user']->stats()->requestBountySize(), 'request-fill-receive-bounty');
        $this->assertEquals($fillBefore['bounty-total'] + 1, $this->userList['user']->stats()->requestBountyTotal(), 'request-fill-receive-total');

        // and now unfill it
        $this->assertEquals(1, $this->request->unfill($this->userList['admin'], 'unfill unittest', new Gazelle\Manager\Torrent), 'request-unfill');
        $this->assertEquals($fillBefore['uploaded'], $this->userList['user']->flush()->uploadedSize(), 'request-fill-unfill-user');
        $this->assertEquals($fillBefore['bounty-total'], $this->userList['user']->stats()->requestBountyTotal(), 'request-fill-unfill-total');

        $requestId = $this->request->id();
        $this->assertTrue($this->request->remove(), 'request-remove');
        unset($this->request); // success, no need to tidy up
        $this->assertNull($requestMan->findById($requestId), 'request-gone');
    }

    public function testJson(): void {
        $this->request = (new Gazelle\Manager\Request)->create(
            userId:          $this->userList['admin']->id(),
            categoryId:      (new Gazelle\Manager\Category)->findIdByName('Music'),
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

        $artistMan  = new Gazelle\Manager\Artist;
        [$artistId, $aliasId] = $artistMan->create('The ' . randomString(8));
        $artistMan->setGroupId($this->request->id());
        $artistMan->addToRequest($artistId, $aliasId, ARTIST_MAIN);

        $this->request->addTag((new Gazelle\Manager\Tag)->create('jazz', $this->userList['admin']));
        $this->assertInstanceOf(Gazelle\Request::class, $this->request, 'request-json-create');

        $json = new Gazelle\Json\Request(
            $this->request,
            $this->userList['user'],
            new Gazelle\User\Bookmark($this->userList['user']),
            new Gazelle\Comment\Request($this->request->id(), 1, 0),
            new Gazelle\Manager\User,
        );
        $payload = $json->payload();
        $this->assertCount(39, $payload, 'req-json-payload');
        $this->assertTrue($payload['canVote'], 'req-json-can-vote');
        $this->assertFalse($payload['canEdit'], 'req-json-can-edit');
        $this->assertEquals($payload['timeAdded'], $payload['lastVote'], 'req-json-date');
        $this->assertEquals('', $payload['fillerName'], 'req-json-can-vote');
        $this->assertEquals('UA-7890', $payload['catalogueNumber'], 'req-json-catno');
    }

    public function testReport(): void {
        $this->request = (new Gazelle\Manager\Request)->create(
            userId:          $this->userList['user']->id(),
            categoryId:      (new Gazelle\Manager\Category)->findIdByName('Comics'),
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

        $manager = new Gazelle\Manager\Report;
        $initial = $manager->remainingTotal();

        $report = $manager->create($this->userList['user'], $this->request->id(), 'request', 'phpunit report');
        $report->setUserManager(new Gazelle\Manager\User);

        $this->assertEquals($initial + 1, $manager->remainingTotal(), 'request-report-one-more');
        $this->assertEquals('New', $report->status(), 'request-report-status-new');
        $this->assertEquals('phpunit report', $report->reason(), 'request-report-reason');
        $this->assertEquals($this->userList['user']->id(), $report->reporter()?->id(), 'request-report-reporter-id');
        $this->assertEquals('request', $report->subjectType(), 'request-report-subject-type');
        $this->assertEquals($this->request->id(), $report->subjectId(), 'request-report-subject-id');
        $this->assertEquals(
            "<a href=\"reports.php?id={$report->id()}#report{$report->id()}\">Report #{$report->id()}</a>",
            $report->link(),
            'request-report-link'
        );
        $this->assertNull($report->notes(), 'request-no-notes-yet');
        $this->assertNull($report->claimer(), 'request-report-no-claimer');
        $this->assertNull($report->resolver(), 'request-report-no-resolver');
        $this->assertNull($report->resolved(), 'request-report-not-resolved-date');
        $this->assertFalse($report->isClaimed(), 'request-report-not-yet-claimed');

        // report specifics
        $reqReport = new Gazelle\Report\Request($report->id(), $this->request);
        $this->assertTrue($reqReport->needReason(), 'request-report-not-an-update');
        $this->assertEquals(
            'Request Report: ' . display_str($this->request->title()),
            $reqReport->title(),
            'request-report-not-an-update'
        );

        // note
        $note = "abc<br />def<br />ghi";
        $report->addNote($note);
        $this->assertEquals("abc\ndef\nghi", $report->notes(), 'request-report-notes');

        // claim
        $this->assertEquals(1, $report->claim($this->userList['admin']), 'request-report-claim');
        $this->assertTrue($report->isClaimed(), 'request-report-is-claimed');
        $this->assertEquals('InProgress', $report->flush()->status(), 'request-report-in-progress');
        $claimer = $report->claimer();
        $this->assertNotNull($claimer, 'request-report-has-claimer');
        $this->assertEquals($this->userList['admin']->id(), $claimer->id(), 'request-report-claimer-id');
        $this->assertEquals(1, $report->claim(null), 'request-report-unclaim');
        $this->assertFalse($report->isClaimed(), 'request-report-is-unclaimed');

        // search
        $this->assertCount(
            1,
            (new Gazelle\Search\Report)->setId($report->id())->page(2, 0),
            'request-report-search-id'
        );
        $this->assertEquals(
            1,
            (new Gazelle\Search\Report)->setStatus(['InProgress'])->total(),
            'request-report-search-in-progress-total'
        );
        $this->assertEquals(
            0,
            (new Gazelle\Search\Report)->setStatus(['InProgress'])->restrictForumMod()->total(),
            'request-report-search-fmod-in-progress-total'
        );

        $search = new Gazelle\Search\Report;
        $total  = $search->setStatus(['InProgress'])->total();
        $page   = $search->page($total, 0);
        $this->assertEquals($total, count($page), 'request-report-page-list');
        $this->assertEquals($report->id(), $page[0], 'request-report-page-id');

        // resolve
        $this->assertEquals(1, $report->resolve($this->userList['admin'], $manager), 'request-report-claim');
        $this->assertNotNull($report->resolved(), 'request-report-resolved-date');
        $this->assertEquals('Resolved', $report->status(), 'request-report-resolved-status');
        $resolver = $report->resolver();
        $this->assertNotNull($resolver, 'request-report-has-resolver');
        $this->assertEquals($this->userList['admin']->id(), $resolver->id(), 'request-report-resolver-id');
        $this->assertEquals($initial, $manager->remainingTotal(), 'request-report-initial-total');
    }
}
