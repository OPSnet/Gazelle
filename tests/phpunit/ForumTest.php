<?php

use \PHPUnit\Framework\TestCase;

class ForumTest extends TestCase {
    protected \Gazelle\Manager\Forum         $forumMan;
    protected \Gazelle\Manager\ForumCategory $fcatMan;
    protected \Gazelle\Manager\ForumPoll     $pollMan;
    protected \Gazelle\Manager\ForumPost     $postMan;
    protected \Gazelle\Manager\ForumThread   $threadMan;
    protected \Gazelle\Manager\User          $userMan;

    public function setUp(): void {
        $this->forumMan  = new \Gazelle\Manager\Forum;
        $this->fcatMan   = new \Gazelle\Manager\ForumCategory;
        $this->pollMan   = new \Gazelle\Manager\ForumPoll;
        $this->postMan   = new \Gazelle\Manager\ForumPost;
        $this->threadMan = new \Gazelle\Manager\ForumThread;
        $this->userMan   = new \Gazelle\Manager\User;
    }

    public function testCategory(): \Gazelle\ForumCategory {
        $initial = count($this->fcatMan->forumCategoryList()); // from phinx seeds

        $category = $this->fcatMan->create('Main', 10);
        $this->assertInstanceOf('\\Gazelle\\ForumCategory', $category, 'forum-cat-is-forum-cat');
        $categoryEphemeral = $this->fcatMan->create('Other', 20);

        $this->assertCount($initial + 2, $this->fcatMan->forumCategoryList(), 'forum-cat-category-list');
        $this->assertCount($initial + 2, $this->fcatMan->usageList(), 'forum-cat-usage-list');

        $find = $this->fcatMan->findById($category->id());
        $this->assertEquals($category->id(), $find->id(), 'forum-cat-find');

        $find->setUpdate('Name', 'new name')->modify();
        $this->assertEquals('new name', $find->name(), 'forum-cat-name');
        $this->assertEquals(10, $find->sequence(), 'forum-cat-sequence');

        $this->assertEquals(1, $categoryEphemeral->remove(), 'forum-cat-remove-unused');
        $this->assertCount($initial + 1, $this->fcatMan->forumCategoryList(), 'forum-cat-category-removed');
        $this->assertCount($initial + 1, $this->fcatMan->usageList(), 'forum-cat-usage-removed');

        return $category;
    }

    /**
     * @depends testCategory
     */
    public function testForum(\Gazelle\ForumCategory $category): \Gazelle\Forum {
        $initial      = count($this->forumMan->nameList());
        $tocTotal     = count($this->forumMan->tableOfContentsMain());
        $user         = $this->userMan->find('@user');
        $userTocTotal = count($this->forumMan->tableOfContents($user));

        $forum = $this->forumMan->create(
            sequence:      150,
            categoryId:    $category->id(),
            name:          'First forum',
            description:   'This is where it happens',
            minRead:       100,
            minWrite:      200,
            minCreate:     300,
            autoLock:      false,
            autoLockWeeks: 42,
        );
        $this->assertInstanceOf('\\Gazelle\\Forum', $forum, 'forum-is-forum');
        $this->assertEquals(0, $category->remove(), 'forum-cat-remove-in-use');
        $this->assertCount($tocTotal + 1, $this->forumMan->tableOfContentsMain(), 'forum-test-toc-main');

        $this->assertFalse($forum->autoLock(), 'forum-autolock');
        $this->assertFalse($forum->hasRevealVotes(), 'forum-has-reveal-votes');
        $this->assertFalse($forum->isLocked(), 'forum-is-locked');
        $this->assertFalse($forum->isSticky(), 'forum-is-sticky');
        $this->assertEquals(0, $forum->lastPostTime(), 'forum-last-post-time');
        $this->assertEquals(0, $forum->numPosts(), 'forum-post-total');
        $this->assertEquals(0, $forum->numThreads(), 'forum-thread-total');
        $this->assertEquals(0, $forum->lastAuthorID(), 'forum-last-author-id');
        $this->assertEquals(42, $forum->autoLockWeeks(), 'forum-autolock-weeks');
        $this->assertEquals(150, $forum->sequence(), 'forum-sequence');
        $this->assertEquals(100, $forum->minClassRead(), 'forum-min-class-read');
        $this->assertEquals(200, $forum->minClassWrite(), 'forum-min-class-write');
        $this->assertEquals(300, $forum->minClassCreate(), 'forum-min-class-create');
        $this->assertEquals($category->name(), $forum->categoryName(), 'forum-category-name');
        $this->assertEquals('This is where it happens', $forum->description(), 'forum-description');
        $this->assertEquals('First forum', $forum->name(), 'forum-name');
        $this->assertNull($forum->lastThread(), 'forum-last-thread');

        $find = $this->forumMan->findById($forum->id());

        $this->assertEquals($forum->id(), $find->id(), 'forum-forum-find');

        $second = $this->forumMan->create(
            sequence:      100,
            categoryId:    $category->id(),
            name:          'Announcements',
            description:   'This is where it begins',
            minRead:       200,
            minWrite:      300,
            minCreate:     700,
            autoLock:      false,
            autoLockWeeks: 52,
        );

        $nameList = $this->forumMan->nameList();
        $this->assertCount($initial + 2, $nameList, 'forum-name-list');
        $this->assertEquals('First forum', $nameList[$forum->id()]['Name'], 'forum-name-list-name-0');
        $this->assertEquals($second->id(), $nameList[$second->id()]['id'], 'forum-name-list-id-1');

        $idList = $this->forumMan->forumList();
        $this->assertCount($initial + 2, $idList, 'forum-id-list-count');
        $this->assertTrue(in_array($second->id(), $idList), 'forum-id-list-sequence');

        $this->assertCount($userTocTotal + 1, $this->forumMan->tableOfContents($user), 'forum-test-toc-user');
        $this->assertEquals(0, $this->forumMan->subscribedForumTotal($user), 'forum-subscribed-total-user');

        return $forum;
    }

    /**
     * @depends testForum
     */
    public function testThread(\Gazelle\Forum $forum): \Gazelle\ForumThread {
        $admin = $this->userMan->find('@admin');

        $thread = $this->threadMan->create($forum, $admin->id(), 'thread title', 'this is a new thread');
        $this->assertEquals(1, $thread->postTotal(), 'fthread-post-total');
        $this->assertEquals(0, $thread->lastPage(), 'fthread-last-page');
        $this->assertEquals(0, $thread->lastCatalog(), 'fthread-last-catalog');
        // weird cache shit
        // $this->assertEquals(1, $forum->numThreads(), 'fthread-admin-number-thread-total');

        $this->assertEquals($admin->id(), $thread->authorId(), 'fthread-author-id');
        $this->assertEquals($admin->username(), $thread->author()->username(), 'fthread-author-username');
        $this->assertEquals($forum->id(), $thread->forumId(), 'fthread-forum-id');
        $this->assertEquals($forum->name(), $thread->forum()->name(), 'fthread-forum-title');
        $this->assertEquals($admin->id(), $thread->lastAuthorId(), 'fthread-forum-title');
        $this->assertEquals('thread title', $thread->title(), 'thread-title');
        $this->assertEquals(0, $thread->pinnedPostId(), 'fthread-pinned-post-id');

        $this->assertFalse($thread->hasPoll(), 'fthread-has-poll-no');
        $this->assertFalse($thread->isLocked(), 'fthread-is-locked');
        $this->assertFalse($thread->isPinned(), 'fthread-is-pinned');

        return $thread;
    }

    /**
     * @depends testThread
     */
    public function testNote(\Gazelle\ForumThread $thread): void {
        $admin  = $this->userMan->find('@admin');

        $threadNote = 'this is a note';
        $id = $thread->addThreadNote($admin->id(), $threadNote);
        $this->assertGreaterThan(0, $id, 'fthread-add-thread-note');

        $notes = $thread->threadNotes();
        $this->assertCount(1, $notes, 'fthread-thread-notes');
        $this->assertEquals($threadNote, $notes[0]['Body'], 'fthread-thread-note-body');
    }

    /**
     * @depends testForum
     */
    public function testAccess(\Gazelle\Forum $forum): void {
        $secretLevel  = $this->userMan->find('@admin')->effectiveClass();
        $secret = $this->forumMan->create(
            sequence:      200,
            categoryId:    $forum->categoryId(),
            name:          'Announcements',
            description:   'This is where mods chat',
            minRead:       $secretLevel,
            minWrite:      $secretLevel,
            minCreate:     $secretLevel,
            autoLock:      false,
            autoLockWeeks: 52,
        );

        $user = $this->userMan->find('@user');
        $this->assertFalse($user->readAccess($secret), 'fthread-secret-user-read');
        $this->assertFalse($user->writeAccess($secret), 'fthread-secret-user-write');
        $this->assertFalse($user->createAccess($secret), 'fthread-secret-user-create');

        $admin = $this->userMan->find('@admin');
        $this->assertTrue($admin->readAccess($secret), 'fthread-secret-admin-read');
        $this->assertTrue($admin->writeAccess($secret), 'fthread-secret-admin-write');
        $this->assertTrue($admin->createAccess($secret), 'fthread-secret-admin-create');

        /* Need to extend modify() to joined tables
        $user->setUpdate('PermittedForums', $secret->id())->modify();
        $this->assertTrue($user->readAccess($secret), 'fthread-secret-user-permitted-read');
        $this->assertTrue($user->writeAccess($secret), 'fthread-secret-user-permitted-write');
        $this->assertTrue($user->createAccess($secret), 'fthread-secret-user-permitted-create');
        */
    }

    /**
     * @depends testThread
     */
    public function testPost(\Gazelle\ForumThread $thread): void {
        $forum  = $thread->forum();

        $user = $this->userMan->find('@user');
        $userSub = new \Gazelle\User\Subscription($user);
        $this->assertFalse($userSub->isSubscribed($thread->id()), 'fpost-user-is-not-subbed');

        $userSub->subscribe($thread->id());
        // FIXME: Kill internal cache
        // $this->assertTrue($userSub->isSubscribed($thread->id()), 'fpost-user-is-now-subbed');
        // $list = $userSub->subscriptionList();
        // $this->assertCount(1, $list, 'fpost-subscriptions-list');
        // $this->assertEquals($thread->id(), $list[0], 'fpost-subscriptions-first');

        $admin = $this->userMan->find('@admin');
        $this->assertEquals(0, $admin->stats()->forumPostTotal(), 'fpost-first-user-stats');
        $message = 'first reply';
        $postId = $thread->addPost($admin->id(), $message);
        // $this->assertEquals(2, $admin->stats()->forumPostTotal(), 'fpost-first-user-reply');

        /* post first reply */
        $post = $this->postMan->findById($postId);
        $this->assertEquals($message, $post->body(), 'fpost-first-post');
        // $this->assertEquals(2, $forum->numPosts(), 'fpost-forum-post-total');

        $adminSub = new \Gazelle\User\Subscription($admin);
        $this->assertEquals(0, $adminSub->unread(), 'fpost-subscriptions-admin-unread');
        $this->assertEquals(1, $userSub->unread(), 'fpost-subscriptions-user-unread');

        /* quote first post in reply */
        $replyId = $thread->addPost($user->id(), "[quote={$admin->username()}|{$post->id()}]{message}[/quote]\ngood job");
        // $this->assertEquals(1, $this->forumMan->unreadSubscribedForumTotal($admin), 'fpost-subscriptions-admin-forum-man-unread');
        // $this->assertEquals(1, $adminSub->unread(), 'fpost-subscriptions-admin-new-unread');

        $quote = new \Gazelle\User\Quote($admin);
        // $this->assertEquals(1, $quote->total(), 'fpost-quote-admin-total');
        // $this->assertEquals(1, $quote->unreadTotal(), 'fpost-quote-admin-unread-total');

        $page = $quote->page(10, 0);
        // $this->assertCount(1, $page, 'fpost-quote-page-count');
        // $this->assertEquals($user->id(), $page[0]['quoter_id'], 'fpost-quote-page-0-quoter');
        // $this->assertEquals($post->url(), $page[0]['jump'], 'fpost-quote-page-0-jump');

        // $this->assertEquals(1, $quote->clearThread($thread->id(), $postId, $replyId), 'fpost-clear-thread');
        // $this->assertEquals(1, $quote->total(), 'fpost-quote-admin-total-clear');
        // $this->assertEquals(0, $quote->unreadTotal(), 'fpost-quote-admin-unread-total-clear');

        $latest = $adminSub->latestSubscriptionList(true, 10, 0);
        // $this->assertCount(1, $latest, 'fpost-subscription-latest-total');
        // $this->assertEquals('forums', $latest[0]['PageID'], 'fpost-quote-admin-unread-page-id');
        // $this->assertEquals($replyId, $latest[0]['PostID'], 'fpost-quote-admin-unread-post-id');

        $thread->catchup($admin->id(), $replyId);
        // $this->assertEquals(0, $adminSub->unread(), 'fpost-subscriptions-admin-new-all-read');

        $readLast = $forum->userLastRead($admin->id(), 50);
        $this->assertCount(1, $readLast, 'forum-last-read-list-total');
        // $this->assertEquals('1/1/1', implode('/', $readLast[0]), 'forum-last-read-list-one');

        // $this->assertEquals(3, $forum->userCatchup($admin->id()), 'forum-user-catchup');
    }

    /**
     * @depends testThread
     */
    public function testPoll(\Gazelle\ForumThread $thread): void {
        $answer = ['apple', 'banana', 'carrot'];
        $poll = $this->pollMan->create($thread->id(), 'Best food', $answer);
        $this->assertInstanceOf('\\Gazelle\\ForumPoll', $poll, 'forum-poll-is-forum-poll');
        $this->assertFalse($poll->isClosed(), 'forum-poll-is-not-closed');
        $this->assertFalse($poll->hasRevealVotes(), 'forum-poll-is-not-featured');
        $this->assertFalse($poll->isFeatured(), 'forum-poll-is-not-featured');
        $this->assertEquals(0, $poll->total(), 'forum-poll-total');
        $this->assertEquals($thread->id(), $poll->thread()->id(), 'forum-poll-thread-id');
        $this->assertCount(3, $poll->vote(), 'forum-poll-vote-count');
        $this->assertEquals($answer[1], $poll->vote()[1]['answer'], 'forum-poll-vote-1');

        $find = $this->pollMan->findById($poll->id());
        $this->assertEquals($poll->id(), $find->id(), 'forum-poll-find-by-id');

        $this->assertEquals(1, $poll->addAnswer('sushi'), 'forum-poll-add-answer');

        $user = $this->userMan->find('@user');
        $this->assertEquals(0, $poll->addVote($user->id(), 12345), 'forum-poll-bad-vote');
        $this->assertEquals(1, $poll->addVote($user->id(), 2),     'forum-poll-good-vote');
        $this->assertEquals(2, $poll->response($user->id()),       'forum-poll-response-before');
        $this->assertEquals(0, $poll->addVote($user->id(), 3),     'forum-poll-revote');
        $this->assertEquals(1, $poll->modifyVote($user->id(), 3),  'forum-poll-change-vote');
        $this->assertEquals(3, $poll->response($user->id()),       'forum-poll-response-after');

        $this->assertEquals(1, $poll->moderate(1, 0), 'forum-poll-moderate-feature-open');
        $this->assertTrue($poll->isFeatured(), 'forum-poll-is-not-featured');

        $this->assertEquals(1, $poll->close(), 'forum-poll-close');
        $this->assertTrue($poll->isClosed(), 'forum-poll-is-closed');
    }
}
