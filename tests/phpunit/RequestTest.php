<?php

use \PHPUnit\Framework\TestCase;

require_once(__DIR__ . '/../../lib/bootstrap.php');
require_once(__DIR__ . '/../helper.php');

class RequestTest extends TestCase {
    protected Gazelle\Manager\Request $requestMan;
    protected Gazelle\Request         $request;
    protected array                   $userList;

    public function setUp(): void {
        $this->requestMan = new Gazelle\Manager\Request;
        $this->userList = [
            'admin' => Helper::makeUser('req1.' . randomString(6), 'request'),
            'user'  => Helper::makeUser('req2.' . randomString(6), 'request'),
        ];
        $this->userList['admin']->setUpdate('Enabled', '1')->setUpdate('PermissionID', SYSOP)->modify();
        $this->userList['user']->setUpdate('Enabled', '1')->modify();
    }

    public function tearDown(): void {
        if (isset($this->request)) {
            // testCreate() removes it for an assertion
            $this->request->remove();
        }
        foreach ($this->userList as $user) {
            $user->remove();
        }
    }

    public function testCreate(): void {
        $userMan  = new Gazelle\Manager\User;
        $statsMan = new Gazelle\Stats\Users;
        $statsMan->refresh();
        $before = [
            'created-size'  => $this->userList['admin']->stats()->requestCreatedSize(),
            'created-total' => $this->userList['admin']->stats()->requestCreatedTotal(),
            'vote-size'     => $this->userList['admin']->stats()->requestVoteSize(),
            'vote-total'    => $this->userList['admin']->stats()->requestVoteTotal(),
            'uploaded'      => $this->userList['admin']->uploadedSize(),
        ];

        $title = 'The ' . randomString(6). ' Test Sessions';
        $this->request = $this->requestMan->create(
            userId:          $this->userList['admin']->id(),
            categoryId:      1,
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

        $tagMan = new Gazelle\Manager\Tag;
        $tagId  = $tagMan->create('jazz', $this->userList['admin']->id());
        $this->assertGreaterThan(0, $tagId, 'request-create-tag');
        $this->assertEquals(1, $tagMan->createRequestTag($tagId, $this->request->id()), 'request-add-tag');

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
        $this->assertEquals(['jazz'], $this->request->tagNameList(), 'request-tag-list');
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
        $this->assertEquals($taxedBounty, $this->request->userBounty($this->userList['admin']->id()), 'request-user-bounty-total');

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

        $this->assertTrue($this->request->remove(), 'request-remove');
        unset($this->request); // success, no need to tidy up
    }

    public function testJson(): void {
        $this->request = $this->requestMan->create(
            userId:          1, // $this->userList['admin']->id(),
            categoryId:      1,
            year:            (int)date('Y'),
            title:           'phpunit request',
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

        $tagMan = new Gazelle\Manager\Tag;
        $tagMan->createRequestTag($tagMan->create('jazz', $this->userList['admin']->id()), $this->request->id());
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
}
