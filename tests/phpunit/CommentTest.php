<?php

namespace Gazelle;

use PHPUnit\Framework\TestCase;

class CommentTest extends TestCase {
    protected Artist  $artist;
    protected Collage $collage;
    protected Request $request;
    protected Torrent $torrent;
    protected User    $user;

    public function setUp(): void {
        $this->user = \GazelleUnitTest\Helper::makeUser('comment.' . randomString(10), 'comment');
    }

    public function tearDown(): void {
        if (isset($this->artist)) {
            $this->artist->remove($this->user);
        }
        if (isset($this->collage)) {
            $this->collage->hardRemove();
        }
        if (isset($this->request)) {
            $this->request->remove();
        }
        if (isset($this->torrent)) {
            \GazelleUnitTest\Helper::removeTGroup($this->torrent->group(), $this->user);
        }
        $this->user->remove();
    }

    public function testCommentArtist(): void {
        $manager = new Manager\Comment();
        $artMan  = new Manager\Artist();
        $this->artist = $artMan->create('phpunit.' . randomString(12));

        $comment = $manager->create($this->user, 'artist', $this->artist->id(), 'phpunit comment ' . randomString(10));
        $this->assertInstanceOf(Comment\Artist::class, $comment, 'comment-artist-create');
        $this->assertEquals('artist', $comment->page(), 'comment-artist-page');
        $this->assertEquals(
            "<a href=\"artist.php?id={$this->artist->id()}&amp;postid={$comment->id()}#post{$comment->id()}\">Comment #{$comment->id()}</a>",
            $comment->link(),
            'comment-artist-link'
        );
        $this->assertEquals(0, $comment->lastRead(), 'comment-artist-last-read');
        $this->assertEquals(0, $comment->pageNum(), 'comment-artist-page-num');

        $reply = $manager->create($this->user, 'artist', $this->artist->id(), 'phpunit reply ' . randomString(10));
        $this->assertInstanceOf(Comment\Artist::class, $comment->load(), 'comment-artist-load');
        $thread = $comment->thread();
        $this->assertCount(2, $thread, 'comment-artist-thread');
        $this->assertCount(2, $comment->threadList(new Manager\User()), 'comment-artist-threadlist');
        $this->assertEquals(2, $comment->total(), 'comment-artist-total');
        $this->assertEquals($this->user->id(), $reply->userId(), 'comment-artist-user-id');
        $this->assertEquals(0, $comment->handleSubscription($this->user), 'comment-artist-handle-subscription');

        $this->assertInstanceOf(
            Comment\Artist::class,
            $manager->findById($comment->id()),
            'comment-artist-find-by-id'
        );
    }

    public function testCommentCollage(): void {
        $this->collage = (new Manager\Collage())->create(
            user:        $this->user,
            categoryId:  1, /* Theme */
            name:        'phpunit collage comment ' . randomString(20),
            description: 'phpunit collage comment description',
            tagList:     'acoustic electronic',
        );

        $manager = new Manager\Comment();
        $body    = 'phpunit comment ' . randomString(10);
        $comment = $manager->create($this->user, 'collages', $this->collage->id(), $body);
        $this->assertEquals($body, $manager->findBodyById($comment->id()), 'comment-find-body');
        $this->assertInstanceOf(Comment\Collage::class, $comment, 'comment-collage-create');
        $this->assertEquals('collages', $comment->page(), 'comment-collage-page');
        $this->assertEquals(
            "<a href=\"collages.php?action=comments&amp;collageid={$this->collage->id()}&amp;postid={$comment->id()}#post{$comment->id()}\">Comment #{$comment->id()}</a>",
            $comment->link(),
            'comment-collage-link'
        );
        $this->assertEquals(1, $manager->remove($comment->page(), $this->collage->id()), 'comment-collage-remove-all');
    }

    public function testCommentRequest(): void {
        $this->request = (new Manager\Request())->create(
            user:            $this->user,
            bounty:          REQUEST_MIN * 1024 * 1024,
            categoryId:      (new Manager\Category())->findIdByName('Music'),
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

        $manager = new Manager\Comment();
        $comment = $manager->create($this->user, 'requests', $this->request->id(), 'phpunit comment ' . randomString(10));
        $this->assertInstanceOf(Comment\Request::class, $comment, 'comment-request-create');
        $this->assertEquals('requests', $comment->page(), 'comment-request-page');
        $this->assertEquals(
            "<a href=\"requests.php?action=view&amp;id={$this->request->id()}&amp;postid={$comment->id()}#post{$comment->id()}\">Comment #{$comment->id()}</a>",
            $comment->link(),
            'comment-request-link'
        );
    }

    public function testCommentTGroup(): void {
        $manager = new Manager\Comment();
        $this->torrent = \GazelleUnitTest\Helper::makeTorrentMusic(
            tgroup: \GazelleUnitTest\Helper::makeTGroupMusic(
                name:       'phpunit comment ' . randomString(6),
                artistName: [[ARTIST_MAIN], ['Comment Sister ' . randomString(12)]],
                tagName:    ['jazz'],
                user:       $this->user,
            ),
            user: $this->user,
        );
        $tgroupId = $this->torrent->groupId();

        $comment = $manager->create($this->user, 'torrents', $tgroupId, 'phpunit comment ' . randomString(10));
        $this->assertInstanceOf(Comment\Torrent::class, $comment, 'comment-torrent-create');
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
            Comment\Torrent::class,
            $manager->findById($comment->id()),
            'comment-torrent-find-by-id'
        );
        $this->assertEquals(1, $manager->remove($comment->page(), $tgroupId), 'comment-torrent-remove-all');
    }

    public function testCommentMerge(): void {
        $manager = new Manager\Comment();
        $artMan  = new Manager\Artist();
        $this->artist = $artMan->create('phpunit.' . randomString(12));
        $artistExtra = $artMan->create('phpunit.' . randomString(12));

        $comment = $manager->create($this->user, 'artist', $this->artist->id(), 'phpunit-merge-keep-artist');
        $manager->create($this->user, 'artist', $artistExtra->id(), 'phpunit-merge-comment');

        $manager->merge('artist', $artistExtra->id(), $this->artist->id());
        $this->assertInstanceOf(Comment\Artist::class, $comment->load(), 'comment-merge-load');
        $this->assertCount(2, $comment->thread(), 'comment-artist-merged-thread');
        $artistExtra->remove($this->user);
    }
}
