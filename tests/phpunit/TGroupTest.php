<?php

use \PHPUnit\Framework\TestCase;

require_once(__DIR__ . '/../../lib/bootstrap.php');
require_once(__DIR__ . '/../helper.php');

class TGroupTest extends TestCase {
    protected Gazelle\TGroup $tgroup;
    protected Gazelle\Manager\TGroup $manager;
    protected string $name;
    protected int $year;
    protected string $recordLabel;
    protected string $catalogueNumber;
    protected array $userList;

    public function setUp(): void {
        $this->userList = [
            'admin' => Helper::makeUser('tgroup.a.' . randomString(6), 'forum'),
            'user'  => Helper::makeUser('tgroup.u.' . randomString(6), 'forum'),
        ];
        $this->userList['admin']->setUpdate('PermissionID', MOD)->modify();

        $this->name            = 'Live in ' . randomString(6);
        $this->year            = (int)date('Y');
        $this->recordLabel     = randomString(6) . ' Records';
        $this->catalogueNumber = randomString(3) . '-' . random_int(1000, 2000);
        $this->manager         = new Gazelle\Manager\TGroup;
        $this->tgroup      = $this->manager->create(
            categoryId:      1,
            name:            $this->name,
            year:            $this->year,
            recordLabel:     $this->recordLabel,
            catalogueNumber: $this->catalogueNumber,
            description:     "Description of {$this->name}",
            image:           '',
            releaseType:     (new Gazelle\ReleaseType)->findIdByName('Live album'),
            showcase:        false,
        );

        // and add some torrents to the group
        Helper::makeTorrentMusic(
            tgroup:          $this->tgroup,
            user:            $this->userList['user'],
            catalogueNumber: 'UA-TG-1',
        );
        Helper::makeTorrentMusic(
            tgroup:          $this->tgroup,
            user:            $this->userList['user'],
            catalogueNumber: 'UA-TG-1',
            format:          'MP3',
            encoding:        'V0',
        );
        Helper::makeTorrentMusic(
            tgroup:          $this->tgroup,
            user:            $this->userList['user'],
            catalogueNumber: 'UA-TG-2',
            title:           'Limited Edition',
        );
    }

    public function tearDown(): void {
        foreach ($this->tgroup->torrentIdList() as $torrent) {
            $torrent = (new \Gazelle\Manager\Torrent)->findById($torrent);
            if (is_null($torrent)) {
                continue;
            }
            [$ok, $message] = $torrent->remove($this->userList['user'], 'tgroup unit test');
            if (!$ok) {
                print "error $message [{$this->userList['user']->id()}]\n";
            }
        }
        $this->tgroup->remove($this->userList['admin']);
        foreach ($this->userList as $user) {
            $user->remove();
        }
    }

    public function testTGroupCreate(): void {
        $this->assertNotNull($this->tgroup, 'tgroup-create-exists');
        $this->assertGreaterThan(1, $this->tgroup->id(), 'tgroup-create-id');

        $this->assertTrue($this->tgroup->categoryGrouped(), 'tgroup-create-category-grouped');
        $this->assertFalse($this->tgroup->isShowcase(), 'tgroup-create-showcase');
        $this->assertFalse($this->tgroup->hasNoCoverArt(), 'tgroup-create-has-no-cover-art');
        $this->assertCount(0, $this->tgroup->revisionList(), 'tgroup-create-revision-list');
        $this->assertEquals('Music', $this->tgroup->categoryName(), 'tgroup-create-category-name');
        $this->assertEquals('cats_music', $this->tgroup->categoryCss(), 'tgroup-create-category-ss');
        $this->assertEquals('Live album', $this->tgroup->releaseTypeName(), 'tgroup-create-release-type-name');
        $this->assertEquals('static/common/noartwork/music.png', $this->tgroup->image(), 'tgroup-create-image');
        $this->assertEquals($this->name, $this->tgroup->name(), 'tgroup-create-name');
        $this->assertEquals($this->year, $this->tgroup->year(), 'tgroup-create-year');
        $this->assertEquals($this->recordLabel, $this->tgroup->recordLabel(), 'tgroup-create-record-label');
        $this->assertEquals($this->catalogueNumber, $this->tgroup->catalogueNumber(), 'tgroup-create-catalogue-number');
        $this->assertEquals(STATIC_SERVER . '/common/noartwork/music.png', $this->tgroup->cover(), 'tgroup-create-cover');
        $this->assertEquals(0, $this->tgroup->unresolvedReportsTotal(), 'tgroup-create-unresolved-reports');
        $this->assertEquals($this->tgroup->name(), $this->tgroup->flush()->name(), 'tgroup-create-flush');

        $this->assertNull($this->manager->findById(-666), 'tgroup-no-instance-of');
        $find = $this->manager->findById($this->tgroup->id());
        $this->assertInstanceOf(Gazelle\TGroup::class, $find, 'tgroup-instance-of');
        $this->assertEquals($this->tgroup->id(), $find->id(), 'tgroup-create-find');
    }

    public function testTGroupArtist(): void {
        $artistName = 'The ' . randomString(6) . ' Band';
        $this->assertEquals(1, $this->tgroup->addArtists([ARTIST_MAIN], [$artistName], $this->userList['admin'], new Gazelle\Manager\Artist, new Gazelle\Log), 'tgroup-artist-add');
        $this->assertEquals("$artistName – {$this->tgroup->name()} [{$this->tgroup->year()} Live album]" , $this->tgroup->text(), 'tgroup-artist-text');

        $this->assertNotNull($this->tgroup->primaryArtist(), 'tgroup-artist-primary');

        $artistRole = $this->tgroup->artistRole();
        $this->assertNotNull($artistRole, 'tgroup-artist-artist-role');

        $idList = $artistRole->idList();
        $this->assertCount(1, $idList, 'tgroup-artist-role-idlist');

        $main = $idList[ARTIST_MAIN];
        $this->assertCount(1, $main, 'tgroup-artist-role-main');

        $first = current($main);
        $this->assertEquals($artistName, $first['name'], 'tgroup-artist-first-name');

        $foundByArtist = $this->manager->findByArtistReleaseYear(
            $this->tgroup->artistRole()->text(),
            $this->tgroup->name(),
            $this->tgroup->releaseType(),
            $this->tgroup->year(),
        );
        $this->assertEquals($this->tgroup->id(), $foundByArtist->id(), 'tgroup-find-name');
    }

    public function testTGroupCoverArt(): void {
        $coverId = $this->tgroup->addCoverArt('https://www.example.com/cover.jpg', 'cover art summary', 1, new Gazelle\Log);
        $this->assertGreaterThan(0, $coverId, 'tgroup-cover-art-add');
        $this->assertEquals(1, $this->tgroup->removeCoverArt($coverId, 1, new Gazelle\Log), 'tgroup-cover-art-del-ok');
        $this->assertEquals(0, $this->tgroup->removeCoverArt(9999999, 1, new Gazelle\Log), 'tgroup-cover-art-del-nok');
    }

    public function testTGroupRevision(): void {
        $revisionId = $this->tgroup->createRevision(
            $this->tgroup->description() . "\nmore text",
            'https://www.example.com/image.jpg',
            'phpunit testTGroup summary',
            $this->userList['admin'],
        );
        $this->assertGreaterThan(0, $revisionId, 'tgroup-revision-add');
    }

    public function testTGroupSubscription(): void {
        $sub = new Gazelle\User\Subscription($this->userList['user']);
        $this->assertTrue($sub->subscribeComments('torrents', $this->tgroup->id()));

        $text       = 'phpunit tgroup subscribe ' . randomString();
        $commentMan = new Gazelle\Manager\Comment;
        $comment    = $commentMan->create($this->userList['admin']->id(), 'torrents', 0, $text);
        // TODO: should this be 1?
        $this->assertEquals(0, $comment->pageNum(), 'tgroup-comment-page-num');

        $this->assertEquals([['torrents', $this->tgroup->id()]], $sub->commentSubscriptions(), 'tgroup-tgroup-comment-sub');
        $this->assertEquals(1, $sub->commentTotal(), 'tgroup-tgroup-comment-all');
    }

    public function testTGroupTag(): void {
        $user = $this->userList['admin'];

        $tagMan = new Gazelle\Manager\Tag;
        $tagId = $tagMan->create('synthetic.disco.punk', $user->id());
        $this->assertGreaterThan(1, $tagId, 'tgroup-tag-create');
        $this->assertEquals(1, $tagMan->createTorrentTag($tagId, $this->tgroup->id(), $user->id(), 10), 'tgroup-tag-add-one');

        $tag2 = $tagMan->create('acoustic.norwegian.black.metal', $user->id());
        $this->assertEquals(1, $tagMan->createTorrentTag($tag2, $this->tgroup->id(), $user->id(), 5), 'tgroup-tag-add-two');
        $this->tgroup->flush();
        $this->assertCount(2, $this->tgroup->tagNameList(), 'tgroup-tag-name-list');
        $this->assertContains('synthetic.disco.punk', $this->tgroup->tagNameList(), 'tgroup-tag-name-find-one');
        $this->assertNotContains('norwegian.black.metal', $this->tgroup->tagNameList(), 'tgroup-tag-name-find-not');
        $this->assertEquals('#synthetic.disco.punk #acoustic.norwegian.black.metal', $this->tgroup->hashTag(), 'tgroup-tag-name-list');

        $this->assertEquals(1, $this->tgroup->addTagVote(2, $tagId, 'up'), 'tgroup-tag-upvote');
        $this->assertEquals(1, $this->tgroup->addTagVote(2, $tag2, 'down'), 'tgroup-tag-downvote');
        $this->assertEquals('Synthetic.disco.punk', $this->tgroup->primaryTag(), 'tgroup-tag-primary');

        $this->assertTrue($this->tgroup->removeTag(new Gazelle\Tag($tag2)), 'tgroup-tag-remove-exists');
        $tag3 = $tagMan->create('disco', $user->id());
        $this->assertFalse($this->tgroup->removeTag(new Gazelle\Tag($tag3)), 'tgroup-tag-remove-not-exists');
    }
}
