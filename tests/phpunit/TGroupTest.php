<?php

use \PHPUnit\Framework\TestCase;

require_once(__DIR__ . '/../../lib/bootstrap.php');

class TGroupTest extends TestCase {
    protected Gazelle\Manager\TGroup $manager;
    protected Gazelle\Manager\User $userMan;
    protected string $recordLabel;
    protected string $catalogueNumber;

    public function setUp(): void {
        $this->manager         = new Gazelle\Manager\TGroup;
        $this->userMan         = new Gazelle\Manager\User;
        $this->recordLabel     = randomString(6) . ' Records';
        $this->catalogueNumber = randomString(3) . '-' . random_int(1000, 2000);
    }

    public function tearDown(): void {}

    public function testRequest() {
        global $DB;
        $tgroupId = $DB->scalar('SELECT ID from torrents_group');
        if ($tgroupId) {
            $tgroup = $this->manager->findById($tgroupId);
            $this->assertInstanceOf('\Gazelle\TGroup', $tgroup, 'tgroup-find-by-id');
        } else {
            $this->assertTrue(true, 'skipped');
        }
    }

    public function testCreate() {
        $name        = 'Live in ' . randomString(6);
        $year        = Date('Y');
        $releaseType = new Gazelle\ReleaseType();
        $tgroup      = $this->manager->create(
            categoryId:      1,
            name:            $name,
            year:            $year,
            recordLabel:     $this->recordLabel,
            catalogueNumber: $this->catalogueNumber,
            description:     "Description of $name",
            image:           '',
            releaseType:     $releaseType->findIdByName('Live album'),
            showcase:        false,
        );
        $this->assertNotNull($tgroup, 'tgroup-create-exists');
        $this->assertGreaterThan(1, $tgroup->id(), 'tgroup-create-id');

        $this->assertTrue($tgroup->categoryGrouped(), 'tgroup-create-category-grouped');
        $this->assertFalse($tgroup->isShowcase(), 'tgroup-create-showcase');
        $this->assertFalse($tgroup->hasNoCoverArt(), 'tgroup-create-has-no-cover-art');
        $this->assertCount(0, $tgroup->revisionList(), 'tgroup-create-revision-list');
        $this->assertEquals('Music', $tgroup->categoryName(), 'tgroup-create-category-name');
        $this->assertEquals('cats_music', $tgroup->categoryCss(), 'tgroup-create-category-ss');
        $this->assertEquals('Live album', $tgroup->releaseTypeName(), 'tgroup-create-release-type-name');
        $this->assertEquals('static/common/noartwork/music.png', $tgroup->image(), 'tgroup-create-image');
        $this->assertEquals($name, $tgroup->name(), 'tgroup-create-name');
        $this->assertEquals($year, $tgroup->year(), 'tgroup-create-year');
        $this->assertEquals($this->recordLabel, $tgroup->recordLabel(), 'tgroup-create-record-label');
        $this->assertEquals($this->catalogueNumber, $tgroup->catalogueNumber(), 'tgroup-create-catalogue-number');
        $this->assertEquals(STATIC_SERVER . '/common/noartwork/music.png', $tgroup->cover(), 'tgroup-create-cover');
        $this->assertEquals(0, $tgroup->unresolvedReportsTotal(), 'tgroup-create-unresolved-reports');
        $this->assertEquals($tgroup->name(), $tgroup->flush()->name(), 'tgroup-create-flush');

        $find = $this->manager->findById($tgroup->id());
        $this->assertEquals($tgroup->id(), $find->id(), 'tgroup-create-find');

        return $tgroup;
    }

    /**
     * @depends testCreate
     */
    public function testArtist(Gazelle\TGroup $tgroup) {
        $artistName = 'The ' . randomString(6) . ' Band';
        $this->assertEquals(1, $tgroup->addArtists(new Gazelle\User(1), [ARTIST_MAIN], [$artistName]), 'tgroup-artist-add');
        $this->assertEquals("$artistName – {$tgroup->name()} [{$tgroup->year()} Live album]" , $tgroup->text(), 'tgroup-artist-text');

        $this->assertNotNull($tgroup->primaryArtist(), 'tgroup-artist-primary');

        $artistRole = $tgroup->artistRole();
        $this->assertNotNull($artistRole, 'tgroup-artist-artist-role');

        $idList = $artistRole->idList();
        $this->assertCount(1, $idList, 'tgroup-artist-role-idlist');

        $main = $idList[ARTIST_MAIN];
        $this->assertCount(1, $main, 'tgroup-artist-role-main');

        $first = current($main);
        $this->assertEquals($artistName, $first['name'], 'tgroup-artist-first-name');
    }

    /**
     * @depends testCreate
     */
    public function testFind(Gazelle\TGroup $tgroup) {
        $foundByArtist = $this->manager->findByArtistReleaseYear(
            $tgroup->artistRole()->text(),
            $tgroup->name(),
            $tgroup->releaseType(),
            $tgroup->year(),
        );
        $this->assertEquals($tgroup->id(), $foundByArtist->id(), 'tgroup-find-name');
    }

    /**
     * @depends testCreate
     */
    public function testCoverArt(Gazelle\TGroup $tgroup) {
        $coverId = $tgroup->addCoverArt('https://www.example.com/cover.jpg', 'cover art summary', 1, new Gazelle\Log);
        $this->assertGreaterThan(0, $coverId, 'tgroup-cover-art-add');
        $this->assertEquals(1, $tgroup->removeCoverArt($coverId, 1, new Gazelle\Log), 'tgroup-cover-art-del-ok');
        $this->assertEquals(0, $tgroup->removeCoverArt(9999999, 1, new Gazelle\Log), 'tgroup-cover-art-del-nok');
    }

    /**
     * @depends testCreate
     */
    public function testRevision(Gazelle\TGroup $tgroup) {
        $revisionId = $tgroup->createRevision(
            $tgroup->description() . "\nmore text",
            'https://www.example.com/image.jpg',
            'phpunit test summary',
            $this->userMan->find('@admin'),
        );
        $this->assertGreaterThan(0, $revisionId, 'tgroup-revision-add');
    }

    /**
     * @depends testCreate
     */
    public function testTag(Gazelle\TGroup $tgroup) {
        $user = $this->userMan->find('@admin');

        $tagMan = new Gazelle\Manager\Tag;
        $tagId = $tagMan->create('synthetic.disco.punk', $user->id());
        $this->assertGreaterThan(1, $tagId, 'tgroup-tag-create');
        $this->assertEquals(1, $tagMan->createTorrentTag($tagId, $tgroup->id(), $user->id(), 10), 'tgroup-tag-add-one');

        $tag2 = $tagMan->create('acoustic.norwegian.black.metal', $user->id());
        $this->assertEquals(1, $tagMan->createTorrentTag($tag2, $tgroup->id(), $user->id(), 5), 'tgroup-tag-add-two');
        $tgroup->flush();
        $this->assertCount(2, $tgroup->tagNameList(), 'tgroup-tag-name-list');
        $this->assertContains('synthetic.disco.punk', $tgroup->tagNameList(), 'tgroup-tag-name-find-one');
        $this->assertNotContains('norwegian.black.metal', $tgroup->tagNameList(), 'tgroup-tag-name-find-not');
        $this->assertEquals('#synthetic.disco.punk #acoustic.norwegian.black.metal', $tgroup->hashtag(), 'tgroup-tag-name-list');

        $this->assertEquals(1, $tgroup->addTagVote(2, $tagId, 'up'), 'tgroup-tag-upvote');
        $this->assertEquals(1, $tgroup->addTagVote(2, $tag2, 'down'), 'tgroup-tag-downvote');
        $this->assertEquals('Synthetic.disco.punk', $tgroup->primaryTag(), 'tgroup-tag-primary');

        $this->assertTrue($tgroup->removeTag(new Gazelle\Tag($tag2)), 'tgroup-tag-remove-exists');
        $tag3 = $tagMan->create('disco', $user->id());
        $this->assertFalse($tgroup->removeTag(new Gazelle\Tag($tag3)), 'tgroup-tag-remove-not-exists');
    }
}
