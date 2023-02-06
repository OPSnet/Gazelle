<?php

use \PHPUnit\Framework\TestCase;

require_once(__DIR__ . '/../../lib/bootstrap.php');

class RequestTest extends TestCase {
    protected Gazelle\Manager\Request $requestMan;
    protected Gazelle\Request $request;

    public function setUp(): void {
        $this->requestMan = new Gazelle\Manager\Request;
    }

    public function tearDown(): void {
        if (isset($this->request)) {
            $this->request->remove();
        }
        // TODO: Make it easier to reset user upload/download
        global $DB;
        $DB->prepared_query("
            UPDATE users_leech_stats SET Uploaded = ? WHERE UserID = ?
            ", STARTING_UPLOAD, (new Gazelle\Manager\User)->find('@user')->id()
        );
    }

    public function testCreate() {
        $userMan  = new Gazelle\Manager\User;
        $admin    = $userMan->find('@admin');
        $user     = $userMan->find('@user');
        $title    = 'The ' . randomString(6). ' Test Sessions';

        $statsMan = new Gazelle\Stats\Users;
        $statsMan->refresh();
        $before = [
            'created-size'  => $admin->stats()->requestCreatedSize(),
            'created-total' => $admin->stats()->requestCreatedTotal(),
            'vote-size'     => $admin->stats()->requestVoteSize(),
            'vote-total'    => $admin->stats()->requestVoteTotal(),
            'uploaded'      => $admin->uploadedSize(),
        ];

        $request = $this->requestMan->create(
            userId:          $admin->id(),
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
        $this->assertInstanceOf(Gazelle\Request::class, $request, 'request-create');
        $this->request = $request; // to allow tear down in case of error

        $artistMan  = new Gazelle\Manager\Artist;
        $artistName = 'The ' . randomString(8);
        [$artistId, $aliasId] = $artistMan->create($artistName);
        $this->assertGreaterThan(0, $artistId, 'request-create-artist');
        // FIXME: nuke this horrible legacy code
        $artistMan->setGroupID($request->id());
        $artistMan->addToRequest($artistId, $aliasId, ARTIST_MAIN);

        $tagMan = new Gazelle\Manager\Tag;
        $tagId  = $tagMan->create('jazz', $admin->id());
        $this->assertGreaterThan(0, $tagId, 'request-create-tag');
        $this->assertEquals(1, $tagMan->createRequestTag($tagId, $request->id()), 'request-add-tag');

        // FIXME: cannot be asserted earlier: this should not depend on a request having tags
        $this->assertInstanceOf(Gazelle\ArtistRole\Request::class, $request->artistRole(), 'request-artist-role');

        // Request::info() will now succeed
        $this->assertEquals(1, $request->userId(), 'request-user-id');
        $this->assertEquals(1, $request->ajaxInfo()['requestorId'], 'request-ajax-user-id');
        $this->assertEquals("$artistName – $title [2018]", $request->text(), 'request-text');

        $this->assertEquals(1, $request->releaseType(), 'request-release-type-id');
        $this->assertEquals('Album', $request->releaseTypeName(), 'request-release-type-name');
        $this->assertEquals(0, $request->fillerId(), 'request-unfilled-filler-id');
        $this->assertEquals(0, $request->torrentId(), 'request-unfilled-torrent-id');
        $this->assertNull($request->fillDate(), 'request-unfilled-date');
        $this->assertNull($request->tgroupId(), 'request-no-tgroup');
        $this->assertEquals('UA-7890', $request->catalogueNumber(), 'request-can-no');
        $this->assertEquals('Unitest Artists', $request->recordLabel(), 'request-rec-label');
        $this->assertEquals(1, $request->categoryId(), 'request-cat-id');
        $this->assertEquals('Music', $request->categoryName(), 'request-cat-name');
        $this->assertEquals(['jazz'], $request->tagNameList(), 'request-tag-list');
        $this->assertEquals('This is a unit test description', $request->description(), 'request-description');
        $this->assertTrue($request->needCue(), 'request-need-cue');
        $this->assertTrue($request->needLog(), 'request-need-log');
        $this->assertTrue($request->needLogChecksum(), 'request-need-checksum');
        $this->assertEquals(100, $request->needLogScore(), 'request-log-score');
        $this->assertEquals('Log (100%) + Cue', $request->descriptionLogCue(), 'request-descn-log-cue');

        $this->assertEquals(['Lossless', 'V0 (VBR)'], array_values($request->currentEncoding()), 'request-cur-encoding');
        $this->assertEquals(['Lossless', 'V0 (VBR)'], array_values($request->needEncodingList()), 'request-need-encoding');
        $this->assertEquals('Lossless, V0 (VBR)', $request->descriptionEncoding(), 'request-descr-encoding');
        $this->assertEquals('Lossless|V0 (VBR)', $request->legacyEncodingList(), 'request-legacy-encoding');
        $this->assertTrue($request->needEncoding('Lossless'), 'request-need-encoding-lossless');
        $this->assertFalse($request->needEncoding('24bit Lossless'), 'request-need-encoding-24bit');
        $this->assertNotEquals(ENCODING, $request->currentEncoding(), 'request-encoding-specified');

        $this->assertEquals(['MP3', 'FLAC'], $request->currentFormat(), 'request-cur-format');
        $this->assertEquals(['MP3', 'FLAC'], $request->needFormatList(), 'request-list-format');
        $this->assertEquals('MP3, FLAC', $request->descriptionFormat(), 'request-descr-format');
        $this->assertEquals('MP3|FLAC', $request->legacyFormatList(), 'request-legacy-format');
        $this->assertTrue($request->needFormat('FLAC'), 'request-need-format-flac');
        $this->assertFalse($request->needFormat('AAC'), 'request-need-format-aac');
        $this->assertNotEquals(FORMAT, $request->currentFormat(), 'request-format-specified');

        $this->assertEquals(['CD', 'WEB'], $request->currentMedia(), 'request-cur-media');
        $this->assertEquals(['CD', 'WEB'], $request->needMediaList(), 'request-list-media');
        $this->assertEquals('CD, WEB', $request->descriptionMedia(), 'request-descr-media');
        $this->assertEquals('CD|WEB', $request->legacyMediaList(), 'request-legacy-media');
        $this->assertTrue($request->needMedia('CD'), 'request-need-media-cd');
        $this->assertFalse($request->needMedia('DVD'), 'request-need-media-DVD');
        $this->assertNotEquals(MEDIA, $request->currentMedia(), 'request-media-specified');

        $this->assertTrue($request->canVote($admin), 'request-vote-owner');
        $this->assertTrue($request->canVote($user), 'request-vote-other');
        $this->assertTrue($request->canEditOwn($admin), 'request-edit-own-owner');
        $this->assertTrue($request->canEdit($admin), 'request-edit-owner');
        $this->assertFalse($request->canEditOwn($user), 'request-edit-own-other');
        $this->assertFalse($request->canEdit($user), 'request-edit-other');

        // Initial vote from creator
        $bounty = 1024**2 * REQUEST_MIN;
        $this->assertTrue($request->vote($admin, $bounty), 'request-initial-bounty');

        $statsMan->refresh();
        $admin->flush();
        $after = [
            'created-size'  => $admin->stats()->requestCreatedSize(),
            'created-total' => $admin->stats()->requestCreatedTotal(),
            'vote-size'     => $admin->stats()->requestVoteSize(),
            'vote-total'    => $admin->stats()->requestVoteTotal(),
            'uploaded'      => $admin->uploadedSize(),
        ];

        $taxedBounty = (int)($bounty * (1 - REQUEST_TAX));
        // $this->assertEquals(1 + $before['created-total'], $after['created-total'], 'request-created-total');
        // $this->assertEquals(1 + $before['vote-total'], $after['vote-total'], 'request-vote-total');
        // $this->assertEquals($taxedBounty, $after['created-size'] - $before['created-size'], 'request-created-size');
        // $this->assertEquals($taxedBounty, $after['vote-size'] - $before['vote-size'], 'request-vote-size');
        $this->assertEquals(-$bounty, $after['uploaded'] - $before['uploaded'], 'request-subtract-bounty');

        $this->assertEquals([$admin->id()], array_column($request->userIdVoteList(), 'user_id'), 'request-user-id-vote-list');
        $this->assertEquals($taxedBounty, $request->userBounty($admin->id()), 'request-user-bounty-total');

        // add some bounty
        $this->assertTrue($request->vote($user, $bounty), 'request-more-bounty');
        $this->assertEquals(2, $request->userVotedTotal(), 'request-total-voted');
        $this->assertEquals(2 * $taxedBounty, $request->bountyTotal(), 'request-total-bounty-added');

        // find a torrent to fill the request
        $fillBefore = [
            'uploaded'     => $user->uploadedSize(),
            'bounty-size'  => $user->stats()->requestBountySize(),
            'bounty-total' => $user->stats()->requestBountyTotal(),
        ];
        global $DB;
        $torrentId = $DB->scalar("SELECT min(ID) FROM torrents");
        $torrent = (new Gazelle\Manager\Torrent)->findById($torrentId);
        $this->assertInstanceOf(Gazelle\Torrent::class, $torrent, 'request-torrent-filler');
        $this->assertEquals(1, $request->fill($user, $torrent), 'request-fill');
        $this->assertEquals($fillBefore['bounty-size'] + $taxedBounty * 2, $user->stats()->requestBountySize(), 'request-fill-receive-bounty');
        $this->assertEquals($fillBefore['bounty-total'] + 1, $user->stats()->requestBountyTotal(), 'request-fill-receive-total');

        // and now unfill it
        $this->assertEquals(1, $request->unfill($admin, 'unfill unittest', new Gazelle\Manager\Torrent), 'request-unfill');
        $this->assertEquals($fillBefore['uploaded'], $user->flush()->uploadedSize(), 'request-fill-unfill-user');
        $this->assertEquals($fillBefore['bounty-total'], $user->stats()->requestBountyTotal(), 'request-fill-unfill-total');

        $this->assertTrue($request->remove(), 'request-remove');
        unset($this->request); // success, no need to tidy up
    }
}
