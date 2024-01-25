<?php

use PHPUnit\Framework\TestCase;

require_once(__DIR__ . '/../../lib/bootstrap.php');
require_once(__DIR__ . '/../helper.php');

class CommentTest extends TestCase {
    protected Gazelle\Artist  $artist;
    protected Gazelle\Collage $collage;
    protected Gazelle\Request $request;
    protected Gazelle\Torrent $torrent;
    protected Gazelle\User    $user;

    public function setUp(): void {
        $this->user = Helper::makeUser('comment.' . randomString(10), 'comment');
    }

    public function tearDown(): void {
        if (isset($this->artist)) {
            $this->artist->remove($this->user, new \Gazelle\Log);
        }
        if (isset($this->collage)) {
            $this->collage->hardRemove();
        }
        if (isset($this->request)) {
            $this->request->remove();
        }
        if (isset($this->torrent)) {
            Helper::removeTGroup($this->torrent->group(), $this->user);
        }
        $this->user->remove();
    }

    public function testCommentArtist(): void {
        $manager = new \Gazelle\Manager\Comment;
        $artMan  = new \Gazelle\Manager\Artist;
        [$artistId, $aliasId] = $artMan->create('phpunit.' . randomString(12));
        $this->artist = $artMan->findById($artistId);

        $comment = $manager->create($this->user, 'artist', $artistId, 'phpunit comment ' . randomString(10));
        $this->assertInstanceOf(\Gazelle\Comment\Artist::class, $comment, 'comment-artist-create');
        $this->assertEquals('artist', $comment->page(), 'comment-artist-page');
        $this->assertEquals(
            "<a href=\"artist.php?id={$artistId}&amp;postid={$comment->id()}#post{$comment->id()}\">Comment #{$comment->id()}</a>",
            $comment->link(),
            'comment-artist-link'
        );
        $this->assertEquals(0, $comment->lastRead(), 'comment-artist-last-read');
        $this->assertEquals(0, $comment->pageNum(), 'comment-artist-page-num');

        $reply = $manager->create($this->user, 'artist', $artistId, 'phpunit reply ' . randomString(10));
        $this->assertInstanceOf(\Gazelle\Comment\Artist::class, $comment->load(), 'comment-artist-load');
        $thread = $comment->thread();
        $this->assertCount(2, $thread, 'comment-artist-thread');
        $this->assertCount(2, $comment->threadList(new \Gazelle\Manager\User), 'comment-artist-threadlist');
        $this->assertEquals(2, $comment->total(), 'comment-artist-total');
        $this->assertEquals($this->user->id(), $reply->userId(), 'comment-artist-user-id');
        $this->assertEquals(0, $comment->handleSubscription($this->user), 'comment-artist-handle-subscription');

        $this->assertInstanceOf(
            \Gazelle\Comment\Artist::class,
            $manager->findById($comment->id()),
            'comment-artist-find-by-id'
        );
    }

    public function testCommentCollage(): void {
        $this->collage = (new Gazelle\Manager\Collage)->create(
            user:        $this->user,
            categoryId:  1, /* Theme */
            name:        'phpunit collage comment ' . randomString(20),
            description: 'phpunit collage comment description',
            tagList:     'acoustic electronic',
            logger:      new Gazelle\Log,
        );

        $manager = new \Gazelle\Manager\Comment;
        $body    = 'phpunit comment ' . randomString(10);
        $comment = $manager->create($this->user, 'collages', $this->collage->id(), $body);
        $this->assertEquals($body, $manager->findBodyById($comment->id()), 'comment-find-body');
        $this->assertInstanceOf(\Gazelle\Comment\Collage::class, $comment, 'comment-collage-create');
        $this->assertEquals('collages', $comment->page(), 'comment-collage-page');
        $this->assertEquals(
            "<a href=\"collages.php?action=comments&amp;collageid={$this->collage->id()}&amp;postid={$comment->id()}#post{$comment->id()}\">Comment #{$comment->id()}</a>",
            $comment->link(),
            'comment-collage-link'
        );
        $this->assertEquals(1, $manager->remove($comment->page(), $this->collage->id()), 'comment-collage-remove-all');
    }

    public function testCommentRequest(): void {
        $this->request = (new Gazelle\Manager\Request)->create(
            user:            $this->user,
            bounty:          REQUEST_MIN * 1024 * 1024,
            categoryId:      (new Gazelle\Manager\Category)->findIdByName('Music'),
            year:            (int)date('Y'),
            title:           'phpunit request comment',
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

        $manager = new \Gazelle\Manager\Comment;
        $comment = $manager->create($this->user, 'requests', $this->request->id(), 'phpunit comment ' . randomString(10));
        $this->assertInstanceOf(\Gazelle\Comment\Request::class, $comment, 'comment-request-create');
        $this->assertEquals('requests', $comment->page(), 'comment-request-page');
        $this->assertEquals(
            "<a href=\"requests.php?action=view&amp;id={$this->request->id()}&amp;postid={$comment->id()}#post{$comment->id()}\">Comment #{$comment->id()}</a>",
            $comment->link(),
            'comment-request-link'
        );
    }

    public function testCommentTGroup(): void {
        $manager = new \Gazelle\Manager\Comment;
        $this->torrent = Helper::makeTorrentMusic(
            tgroup: Helper::makeTGroupMusic(
                name:       'phpunit comment ' . randomString(6),
                artistName: [[ARTIST_MAIN], ['Comment Sister ' . randomString(12)]],
                tagName:    ['jazz'],
                user:       $this->user,
            ),
            user: $this->user,
        );
        $tgroupId = $this->torrent->groupId();

        $comment = $manager->create($this->user, 'torrents', $tgroupId, 'phpunit comment ' . randomString(10));
        $this->assertInstanceOf(\Gazelle\Comment\Torrent::class, $comment, 'comment-torrent-create');
        $this->assertEquals('torrents', $comment->page(), 'comment-torrent-page');
        $this->assertEquals('torrents.php?id=', $comment->pageUrl(), 'comment-torrent-page-url');

        $this->assertTrue($comment->isAuthor($this->user), 'comment-torrent-is-author');
        $this->assertStringStartsWith('phpunit comment ', $comment->body(), 'comment-torrent-body');
        $this->assertTrue(
            $comment->setField('Body', 'phpunit edit')
                ->setField('EditedUserID', $this->user->id())
                ->modify(),
            'comment-torrent-edit'
        );
        $this->assertCount(1, $manager->loadEdits($comment->page(), $comment->id()), 'comment-torrent-load-edits');

        $this->assertInstanceOf(
            \Gazelle\Comment\Torrent::class,
            $manager->findById($comment->id()),
            'comment-torrent-find-by-id'
        );
        $this->assertEquals(1, $manager->remove($comment->page(), $tgroupId), 'comment-torrent-remove-all');
    }

    public function testCommentMerge(): void {
        $manager = new \Gazelle\Manager\Comment;
        $artMan  = new \Gazelle\Manager\Artist;
        [$artistId, $aliasId] = $artMan->create('phpunit.' . randomString(12));
        $this->artist = $artMan->findById($artistId);

        [$artistExtraId, $aliasExtraId] = $artMan->create('phpunit.' . randomString(12));
        $artistExtra = $artMan->findById($artistExtraId);

        $comment = $manager->create($this->user, 'artist', $this->artist->id(), 'phpunit-merge-keep-artist');
        $extra = $manager->create($this->user, 'artist', $artistExtraId, 'phpunit-merge-comment');

        $manager->merge('artist', $artistExtraId, $this->artist->id());
        $this->assertInstanceOf(\Gazelle\Comment\Artist::class, $comment->load(), 'comment-merge-load');
        $this->assertCount(2, $comment->thread(), 'comment-artist-merged-thread');
        $artistExtra->remove($this->user, new \Gazelle\Log);
    }
}
