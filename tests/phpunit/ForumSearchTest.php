<?php

use PHPUnit\Framework\TestCase;

class ForumSearchTest extends TestCase {
    protected Gazelle\ForumCategory $category;
    protected Gazelle\Forum         $forum;
    protected Gazelle\ForumThread   $thread;
    protected Gazelle\User          $user;

    public function setUp(): void {
        $this->user = Helper::makeUser('user.' . randomString(10), 'forum');
    }

    public function tearDown(): void {
        $this->thread->remove();
        $this->forum->remove();
        $this->category->remove();
        $this->user->remove();
    }

    public function testForumSearchThread(): void {
        $this->category = (new \Gazelle\Manager\ForumCategory())->create('phpunit category', 10001);
        $this->forum  = Helper::makeForum(
            user:        $this->user,
            sequence:    250,
            category:    $this->category,
            name:        'Search forum',
            description: 'This is where it is found',
        );
        $title        = 'search thread ' . randomString(10);
        $body         = 'search body ' . randomString(10);
        $threadMan    = new \Gazelle\Manager\ForumThread();
        $this->thread = $threadMan->create($this->forum, $this->user, $title, $body);

        $search = new \Gazelle\Search\Forum($this->user);
        $this->assertInstanceOf(\Gazelle\Search\Forum::class, $search, 'forum-search-new');
        $this->assertFalse($search->isBodySearch(), 'forum-search-title');

        $search->setSearchText('textnotfound ' . randomString(40));
        $this->assertEquals(0, $search->totalHits(), 'forum-search-title-miss');
        $search->setSearchText($title);
        $this->assertEquals(1, $search->totalHits(), 'forum-search-title-hit');

        $search->setSearchType('body');
        $this->assertTrue($search->isBodySearch(), 'forum-search-body');
        $search->setSearchText('textnotfound ' . randomString(40));
        $this->assertEquals(0, $search->totalHits(), 'forum-search-body-miss');
        $search->setSearchText($body);
        $this->assertEquals(1, $search->totalHits(), 'forum-search-body-hit');

        $resultList = $search->results(new \Gazelle\Util\Paginator(25, 1));
        $this->assertCount(1, $resultList, 'forum-search-body-result');
        $result = current($resultList);
        $this->assertEquals($this->thread->id(), $result[0], 'forum-search-body-thread-id');
        $this->assertEquals($this->thread->title(), $result[1], 'forum-search-body-thread-title');
        $this->assertEquals($this->forum->id(), $result[2], 'forum-search-body-thread-forum-id');
        $this->assertEquals($this->forum->name(), $result[3], 'forum-search-body-thread-forum-name');
        // 4 is post created
        // 5 is post id
        $this->assertEquals($body, $result[6], 'forum-search-body-thread-forum-body');
        $this->assertEquals($this->thread->created(), $result[7], 'forum-search-body-thread-created');
    }

    public function testForumSearchAuthor(): void {
        $this->category = (new \Gazelle\Manager\ForumCategory())->create('phpunit category', 10001);
        $this->forum  = Helper::makeForum(
            user:        $this->user,
            sequence:    250,
            category:    $this->category,
            name:        'Search forum',
            description: 'This is where it is found',
        );
        $title        = 'search thread ' . randomString(10);
        $body         = 'search body ' . randomString(10);
        $threadMan    = new \Gazelle\Manager\ForumThread();
        $this->thread = $threadMan->create($this->forum, $this->user, $title, $body);

        $search = new \Gazelle\Search\Forum($this->user);
        $this->assertInstanceOf(\Gazelle\Search\Forum::class, $search->setAuthor($this->user->username()), 'forum-search-author');
        $this->assertEquals(1, $search->totalHits(), 'forum-search-user-hit');

        $search->setAuthor('nobody-by-this-name!');
        $this->assertEquals(0, $search->totalHits(), 'forum-search-user-miss');
    }
}
