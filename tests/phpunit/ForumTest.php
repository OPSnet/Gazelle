<?php

use PHPUnit\Framework\TestCase;

require_once(__DIR__ . '/../../lib/bootstrap.php');
require_once(__DIR__ . '/../helper.php');

/**
 * In the original Gazelle implementation, two sets of developers implemented
 * two distinct methods of keeping track of unread posts by users. As the code
 * has migrated away from sections/forum to this class, the contradiction
 * becomes more apparent. At some point these approaches will converge.
 */

class ForumTest extends TestCase {
    protected Gazelle\ForumCategory $category;
    protected Gazelle\Forum         $forum;
    protected Gazelle\Forum         $extra;
    protected array                 $userList;
    protected array                 $threadList;
    protected array                 $transitionList;

    public function setUp(): void {
        $this->userList = [
            'admin' => Helper::makeUser('admin.' . randomString(10), 'forum'),
            'user'  => Helper::makeUser('user.' . randomString(10), 'forum'),
        ];
        $this->userList['admin']->setField('PermissionID', SYSOP)->modify();
    }

    public function tearDown(): void {
        if (isset($this->transitionList)) {
            foreach ($this->transitionList as $transition) {
                $transition->remove();
            }
        }
        if (isset($this->threadList)) {
            foreach ($this->threadList as $thread) {
                $thread->remove();
            }
        }
        if (isset($this->extra)) {
            $this->extra->remove();
        }
        $this->forum->remove();
        $this->category->remove();
        foreach ($this->userList as $user) {
            $user->remove();
        }
    }

    public function testForumCreate(): void {
        // Forum Categories
        $fcatMan = new \Gazelle\Manager\ForumCategory();
        $initial = count($fcatMan->forumCategoryList()); // from phinx seeds

        // If you hit a duplicate key error here it is due to an aborted previous test run
        $this->category = $fcatMan->create('phpunit category', 10001);
        $this->assertInstanceOf(\Gazelle\ForumCategory::class, $this->category, 'forum-cat-is-forum-cat');
        $this->assertEquals(0, $this->category->forumTotal(), 'forum-category-forum-none');

        $categoryEphemeral = $fcatMan->create('phpunit other', 10002);
        $this->assertCount($initial + 2, $fcatMan->forumCategoryList(), 'forum-cat-category-list');
        $this->assertCount($initial + 2, $fcatMan->usageList(), 'forum-cat-usage-list');

        $find = $fcatMan->findById($this->category->id());
        $find->setField('Name', 'phpunit renamed')->modify();
        $this->assertEquals($this->category->id(), $find->id(), 'forum-cat-find');
        $this->assertEquals('phpunit renamed', $find->name(), 'forum-cat-name');
        $this->assertEquals(10001, $find->sequence(), 'forum-cat-sequence');
        $this->assertEquals(1, $categoryEphemeral->remove(), 'forum-cat-remove-unused');
        $this->assertCount($initial + 1, $fcatMan->forumCategoryList(), 'forum-cat-category-removed');
        $this->assertCount($initial + 1, $fcatMan->usageList(), 'forum-cat-usage-removed');

        // Forums
        $forumMan     = new \Gazelle\Manager\Forum();
        $initial      = count($forumMan->nameList());
        $tocTotal     = count($forumMan->tableOfContentsMain());
        $admin        = $this->userList['admin'];
        $user         = $this->userList['user'];
        $userTocTotal = count($forumMan->tableOfContents($user));
        $forumName    = 'phpunit first forum';
        $this->forum  = Helper::makeForum(
            user:           $admin,
            sequence:       150,
            category:       $this->category,
            name:           $forumName,
            description:    'This is where it happens',
            minClassRead:   100,
            minClassWrite:  200,
            minClassCreate: 300,
        );
        $this->assertEquals(1, $this->category->forumTotal(), 'forum-category-forum-total');
        $this->assertInstanceOf(\Gazelle\Forum::class, $this->forum, 'forum-is-forum');
        $this->assertEquals(0, $this->category->remove(), 'forum-cat-remove-in-use');
        // If you hit a mismatch here it is due to an aborted previous test run
        $this->assertCount($tocTotal + 1, $forumMan->tableOfContentsMain(), 'forum-test-toc-main');
        $this->forum->userCatchup($admin);

        $this->assertFalse($this->forum->autoLock(), 'forum-autolock');
        $this->assertFalse($this->forum->hasRevealVotes(), 'forum-has-reveal-votes');
        $this->assertFalse($this->forum->isLocked(), 'forum-is-locked');
        $this->assertEquals(0, $this->forum->lastPostEpoch(), 'forum-last-post-time');
        $this->assertEquals(0, $this->forum->numPosts(), 'forum-post-total');
        $this->assertEquals(0, $this->forum->numThreads(), 'forum-thread-total');
        $this->assertEquals($admin->id(), $this->forum->lastAuthorId(), 'forum-last-author-id');
        $this->assertEquals(42, $this->forum->autoLockWeeks(), 'forum-autolock-weeks');
        $this->assertEquals(150, $this->forum->sequence(), 'forum-sequence');
        $this->assertEquals(100, $this->forum->minClassRead(), 'forum-min-class-read');
        $this->assertEquals(200, $this->forum->minClassWrite(), 'forum-min-class-write');
        $this->assertEquals(300, $this->forum->minClassCreate(), 'forum-min-class-create');
        $this->assertEquals($this->category->name(), $this->forum->categoryName(), 'forum-category-name');
        $this->assertEquals('This is where it happens', $this->forum->description(), 'forum-description');
        $this->assertEquals($forumName, $this->forum->name(), 'forum-name');
        $this->assertNull($this->forum->lastThread(), 'forum-last-thread');

        $find = $forumMan->findById($this->forum->id());
        $this->assertEquals($this->forum->id(), $find->id(), 'forum-forum-find');

        $this->extra = Helper::makeForum(
            user:           $this->userList['admin'],
            sequence:       100,
            category:       $this->category,
            name:           'phpunit announcements',
            description:    'This is where it begins',
            minClassRead:   200,
            minClassWrite:  300,
            minClassCreate: 700,
            autoLock:       false,
            autoLockWeeks:  52,
        );

        $nameList = $forumMan->nameList();
        $this->assertCount($initial + 2, $nameList, 'forum-name-list');
        $this->assertEquals($forumName, $nameList[$this->forum->id()]['Name'], 'forum-name-list-name-0');
        $this->assertEquals($this->extra->id(), $nameList[$this->extra->id()]['id'], 'forum-name-list-id-1');

        $forumList = $forumMan->forumList();
        $idList = array_map(fn ($f) => $f->id(), $forumList);
        $this->assertCount($initial + 2, $idList, 'forum-id-list-count');
        $this->assertTrue(in_array($this->extra->id(), $idList), 'forum-id-list-sequence');
        $this->assertCount($userTocTotal + 1, $forumMan->tableOfContents($user), 'forum-test-toc-user');
        $this->assertEquals(0, $forumMan->subscribedForumTotal($user), 'forum-subscribed-total-user');

        // Forum Threads
        $threadMan = new \Gazelle\Manager\ForumThread();
        $thread    = $threadMan->create($this->forum, $admin, 'thread title', 'this is a new thread');
        $this->assertEquals('this is a new thread', $thread->body(), 'fthread-body');
        $this->assertEquals(1, $thread->postTotal(), 'fthread-post-total');
        $this->assertEquals(0, $thread->lastPage(), 'fthread-last-page');
        $this->assertEquals(0, $thread->lastCatalogue(), 'fthread-last-catalog');
        $this->assertEquals(1, $this->forum->numThreads(), 'fthread-admin-number-thread-total');

        $this->assertEquals($admin->id(), $thread->authorId(), 'fthread-author-id');
        $this->assertEquals($admin->username(), $thread->author()->username(), 'fthread-author-username');
        $this->assertEquals($this->forum->id(), $thread->forumId(), 'fthread-forum-id');
        $this->assertEquals($this->forum->name(), $thread->forum()->name(), 'fthread-forum-title');
        $this->assertEquals($admin->id(), $thread->lastAuthorId(), 'fthread-forum-title');
        $this->assertEquals('thread title', $thread->title(), 'thread-title');
        $this->assertEquals(0, $thread->pinnedPostId(), 'fthread-pinned-post-id');

        $this->assertFalse($thread->hasPoll(), 'fthread-has-poll-no');
        $this->assertFalse($thread->isLocked(), 'fthread-is-not-locked');
        $this->assertFalse($thread->isPinned(), 'fthread-is-not-pinned');

        $this->assertEquals(1, $admin->stats()->forumThreadTotal(), 'fthread-user-stats-total');
        $this->assertCount(0, $this->userList['user']->forumLastReadList(1, $this->forum), 'fthread-user-unread');

        // Forum Thread Notes
        $threadNote = 'this is a note';
        $id = $thread->addThreadNote($admin, $threadNote);
        $this->assertGreaterThan(0, $id, 'fthread-add-thread-note');

        $notes = $thread->threadNotes();
        $this->assertCount(1, $notes, 'fthread-thread-notes');
        $this->assertEquals($threadNote, $notes[0]['Body'], 'fthread-thread-note-body');

        // Forum ACLs
        $secretLevel = $admin->privilege()->effectiveClassLevel();
        $secret = Helper::makeForum(
            user:           $admin,
            sequence:       200,
            category:       $this->category,
            name:           'phpunit chit-chat',
            description:    'This is where mods chat',
            minClassRead:   $secretLevel,
            minClassWrite:  $secretLevel,
            minClassCreate: $secretLevel,
        );

        $user = $this->userList['user'];
        $this->assertFalse($user->readAccess($secret), 'fthread-secret-user-read');
        $this->assertFalse($user->writeAccess($secret), 'fthread-secret-user-write');
        $this->assertFalse($user->createAccess($secret), 'fthread-secret-user-create');

        $this->assertTrue($admin->readAccess($secret), 'fthread-secret-admin-read');
        $this->assertTrue($admin->writeAccess($secret), 'fthread-secret-admin-write');
        $this->assertTrue($admin->createAccess($secret), 'fthread-secret-admin-create');

        $user->setField('PermittedForums', $secret->id())->modify();
        $this->assertTrue($user->readAccess($secret), 'fthread-secret-user-permitted-read');
        $this->assertTrue($user->writeAccess($secret), 'fthread-secret-user-permitted-write');
        $this->assertTrue($user->createAccess($secret), 'fthread-secret-user-permitted-create');
        $this->assertEquals(1, $secret->remove(), 'forum-secret-remove');

        // Forum Posts
        $userSub = new \Gazelle\User\Subscription($user);
        $this->assertFalse($userSub->isSubscribed($thread->id()), 'fpost-user-is-not-subbed');

        $userSub->subscribe($thread->id());
        $this->assertTrue($userSub->isSubscribed($thread->id()), 'fpost-user-is-now-subbed');
        $list = $userSub->subscriptionList();
        $this->assertCount(1, $list, 'fpost-subscriptions-list');
        $this->assertEquals($thread->id(), $list[0], 'fpost-subscriptions-first');

        $admin = $this->userList['admin'];
        $this->assertEquals(1, $admin->stats()->forumPostTotal(), 'fpost-first-user-stats');
        $message = 'first reply';
        $post = $thread->addPost($admin, $message);
        $this->assertEquals(2, $admin->stats()->flush()->forumPostTotal(), 'fpost-first-user-reply');
        $this->assertEquals($post->id(), $this->forum->flush()->lastPostId(), 'fpost-is-last-post');

        /* post first reply */
        $postMan = new \Gazelle\Manager\ForumPost();
        $this->assertEquals($message, $post->body(), 'fpost-first-post');
        $this->assertEquals(2, $this->forum->numPosts(), 'fpost-forum-post-total');

        $this->assertEquals(1, $userSub->unread(), 'fpost-subscriptions-user-unread');
        $adminSub = new \Gazelle\User\Subscription($admin); // now sub them
        $this->assertEquals(0, $adminSub->unread(), 'fpost-subscriptions-admin-unread');
        $adminSub->subscribe($thread->id());

        /* quote first post in reply */
        $body = "good job @{$admin->username()}";
        $reply = $thread->addPost($user, $body);
        // Should the following actions (quote and subscription handling) be performed by the addPost() method?
        (new Gazelle\User\Notification\Quote($admin))->create(
            new Gazelle\Manager\User(),
            $body,
            $reply->id(),
            'forums',
            $thread->id(),
        );
        (new Gazelle\Manager\Subscription())->flushPage('forums', $thread->id());

        $this->assertEquals(1, $forumMan->unreadSubscribedForumTotal($admin), 'fpost-subscriptions-admin-forum-man-unread');
        $this->assertEquals(1, $adminSub->flush()->unread(), 'fpost-subscriptions-admin-new-unread');

        $quote = new Gazelle\User\Quote($admin);
        $this->assertEquals(1, $quote->total(), 'fpost-quote-admin-total');
        $this->assertEquals(1, $quote->unreadTotal(), 'fpost-quote-admin-unread-total');

        $page = $quote->page(10, 0);
        $this->assertCount(1, $page, 'fpost-quote-page-count');
        $this->assertEquals($admin->id(), $page[0]['quoter_id'], 'fpost-quote-page-0-quoter');
        $this->assertEquals($postMan->findById($reply->id())->url(), $page[0]['jump'], 'fpost-quote-page-0-jump');

        $this->assertEquals(1, $quote->clearThread($thread->id(), $post->id(), $reply->id()), 'fpost-clear-thread');
        $this->assertEquals(0, $quote->total(), 'fpost-quote-admin-total-clear');
        $this->assertEquals(0, $quote->unreadTotal(), 'fpost-quote-admin-unread-total-clear');

        $latest = $adminSub->latestSubscriptionList(true, 10, 0);
        $this->assertCount(1, $latest, 'fpost-subscription-latest-total');
        $this->assertEquals($thread->id(), $latest[0]['PageID'], 'fpost-quote-admin-unread-page-id');
        $this->assertNull($latest[0]['PostID'], 'fpost-quote-admin-unread-post-id');

        $thread->catchup($admin, $reply->id());
        $this->assertEquals(1, $adminSub->unread(), 'fpost-subscriptions-admin-one-read');

        $readLast = $this->forum->userLastRead($admin);
        $this->assertCount(1, $readLast, 'forum-last-read-list-total');
        $this->assertEquals(
            [
                $thread->id() => [
                    "TopicID" => $thread->id(),
                    "PostID"  => $reply->id(),
                    "Page"    => 1,
                ]
            ],
            $readLast,
            'forum-last-read-list-one'
        );
        $this->assertCount(1, $this->userList['admin']->forumLastReadList(1, $this->forum), 'fthread-user-unread');

        $this->assertEquals(5, $thread->remove(), 'forum-thread-remove');
    }

    public function testForumAutoSub(): void {
        $this->category = (new \Gazelle\Manager\ForumCategory())->create('phpunit category', 10010);
        $this->forum    = Helper::makeForum(
            user:        $this->userList['admin'],
            sequence:    151,
            category:    $this->category,
            name:        'phpunit autosub forum',
            description: 'This is where it autosubs',
        );

        $user = $this->userList['user'];
        $this->assertFalse($this->forum->isAutoSubscribe($user), 'forum-autosub-not-autosub');
        $this->assertEquals(1, $this->forum->toggleAutoSubscribe($user, true), 'forum-autosub-toggle-autosub');
        $this->assertTrue($this->forum->isAutoSubscribe($user), 'forum-autosub-now-autosub');
        $this->assertEquals(0, $this->forum->toggleAutoSubscribe($user, true), 'forum-autosub-toggle-none');

        $this->assertEquals([$user->id()], $this->forum->autoSubscribeUserIdList(), 'forum-autosub-userlist');
        $this->assertEquals([], $this->forum->autoSubscribeForUserList($user), 'forum-autosub-forum-list');
        $user->addCustomPrivilege('site_forum_autosub');
        $this->assertEquals([$this->forum->id()], $this->forum->autoSubscribeForUserList($user), 'forum-autosub-forum-list');

        $threadMan = new \Gazelle\Manager\ForumThread();
        $this->threadList[] = $threadMan->create($this->forum, $this->userList['admin'], 'phpunit thread title', 'this is a new thread');
        $this->threadList[] = $threadMan->create($this->forum, $this->userList['admin'], 'phpunit thread title 2', 'this is also a new thread');
        $this->assertEquals(2, $this->forum->userCatchup($user), 'forum-user-autosub-catchup');

        $this->assertEquals(1, $this->forum->toggleAutoSubscribe($user, false), 'forum-autosub-off-autosub');
        $this->assertFalse($this->forum->isAutoSubscribe($user), 'forum-autosub-no-longer-autosub');
        $this->assertEquals([], $this->forum->autoSubscribeUserIdList(), 'forum-autosub-no-userlist');
    }

    public function testForumForbidden(): void {
        $this->category = (new \Gazelle\Manager\ForumCategory())->create('phpunit category', 10002);
        $forumMan       = new \Gazelle\Manager\Forum();
        $user           = $this->userList['user'];
        $this->forum    = $forumMan->create(
            user:           $this->userList['admin'],
            sequence:       151,
            categoryId:     $this->category->id(),
            name:           'phpunit forbid forum',
            description:    'This is where it forbids',
            minClassRead:   100,
            minClassWrite:  100,
            minClassCreate: 100,
            autoLock:       false,
            autoLockWeeks:  42,
        );
        $this->assertTrue($user->readAccess($this->forum), 'forum-forbid-read-allowed');
        $this->assertTrue($user->writeAccess($this->forum), 'forum-forbid-write-allowed');
        $this->assertTrue($user->createAccess($this->forum), 'forum-forbid-create-allowed');

        $user->setField('RestrictedForums', $this->forum->id())->modify();
        $this->assertFalse($user->readAccess($this->forum), 'forum-forbid-read-denied');
        $this->assertFalse($user->writeAccess($this->forum), 'forum-forbid-write-denied');
        $this->assertFalse($user->createAccess($this->forum), 'forum-forbid-create-denied');
    }

    public function testForumJson(): void {
        $this->category = (new \Gazelle\Manager\ForumCategory())->create('phpunit category', 10002);
        $forumMan       = new \Gazelle\Manager\Forum();
        $this->forum    = Helper::makeForum(
            user:        $this->userList['admin'],
            sequence:    151,
            category:    $this->category,
            name:        'phpunit json forum',
            description: 'This is where it json',
        );

        $json = (new Gazelle\Json\Forum(
            $this->forum,
            $this->userList['user'],
            new Gazelle\Manager\ForumThread(),
            new Gazelle\Manager\User(),
            1,
            1,
        ));
        $this->assertInstanceOf(Gazelle\Json::class, $json, 'forum-json-class');
        $response = json_decode($json->response(), true);
        $info = $response['response'];
        $this->assertEquals($this->forum->name(), $info['forumName'], 'forum-json-name');
        $this->assertEquals(0, $info['pages'], 'forum-json-pages');
        $this->assertEquals(1, $info['currentPage'], 'forum-json-current-pages');
        $this->assertCount(0, $info['threads'], 'forum-json-threads');

        (new \Gazelle\Manager\ForumThread())
            ->create($this->forum, $this->userList['admin'], 'thread title', 'this is a new thread');
        $response = json_decode($json->response(), true);
        $info = $response['response'];
        $this->assertEquals(1, $info['pages'], 'forum-json-new-pages');
        $this->assertCount(1, $info['threads'], 'forum-json-new-threads');
    }

    public function testForumThreadJson(): void {
        $this->category = (new \Gazelle\Manager\ForumCategory())->create('phpunit category', 10002);
        $forumMan       = new \Gazelle\Manager\Forum();
        $this->forum    = Helper::makeForum(
            user:        $this->userList['admin'],
            sequence:    151,
            category:    $this->category,
            name:        'phpunit json thread',
            description: 'This is where it thread json',
        );

        $threadMan = new \Gazelle\Manager\ForumThread();
        $thread    = $threadMan->create(
            $this->forum,
            $this->userList['admin'],
            'thread json title',
            'this is a new json thread'
        );

        $json = (new Gazelle\Json\ForumThread(
            $thread,
            $this->userList['user'],
            new Gazelle\Util\Paginator(25, 1),
            true,
            new Gazelle\Manager\User(),
        ));
        $this->assertInstanceOf(Gazelle\Json::class, $json, 'forum-json-class');
        $response = json_decode($json->response(), true);
        $result = $response['response'];
        $this->assertEquals($thread->forumId(), $result['forumId'], 'json-thread-forum-id');
        $this->assertCount(1, $result['posts'], 'json-thread-post-total');
        $this->assertEquals(
            $this->userList['admin']->username(),
            $result['posts'][0]['author']['authorName'], 'json-thread-first-post-author'
        );
    }

    public function testForumWarn(): void {
        $this->category = (new \Gazelle\Manager\ForumCategory())->create('phpunit category', 10002);
        $forumMan       = new \Gazelle\Manager\Forum();
        $admin          = $this->userList['admin'];
        $user           = $this->userList['user'];
        $this->forum    = Helper::makeForum(
            user:        $admin,
            sequence:    151,
            category:    $this->category,
            name:        'phpunit warn forum',
            description: 'This is where it warns',
        );
        $pmMan = new Gazelle\Manager\PM($user);
        foreach ($user->inbox()->messageList($pmMan, 1, 0) as $pm) {
            $pm->remove();
        }

        // TODO: move more warning functionality out of sections/...
        $this->threadList[] = $thread
            = (new \Gazelle\Manager\ForumThread())->create($this->forum, $user, 'user thread title', 'this is a new thread by a user');
        $thread  = (new \Gazelle\Manager\ForumThread())->create($this->forum, $user, 'user thread title', 'this is a new thread by a user');
        $post    = $thread->addPost($user, 'offensive content');
        $week    = 2;
        $message = "phpunit forum warn test " . randomString(10);
        $this->assertNull($user->forumWarning(), 'forum-post-no-warning-history');
        $user->warnPost($post, $week, $admin, "{$post->location()} - because phpunit", $message);
        $this->assertTrue($user->addForumWarning($message)->modify(), 'forum-post-add-warning');
        // phpstan does not realize that forumWarning() is volatile and so it considers that the value is still null when in fact it is a string
        $this->assertStringStartsWith(date('Y-m-d H'), $user->forumWarning(), 'forum-user-warning-history-start'); /** @phpstan-ignore-line */
        $this->assertStringEndsWith($message, $user->forumWarning(), 'forum-user-warning-history-end'); /** @phpstan-ignore-line */

        $inbox = $user->inbox();
        $pmReceiverManager = new Gazelle\Manager\PM($inbox->user());
        $this->assertEquals(1, $inbox->messageTotal(), 'warn-user-inbox-total');
        $pm = $inbox->messageList($pmReceiverManager, 1, 0)[0];
        $this->assertEquals('You have been warned', $pm->subject(), 'warn-user-inbox-pm-subject');
        $postInfo = $pm->postList(1, 0)[0];
        $body = $postInfo['body'];
        $this->assertMatchesRegularExpression(
            "/You have been warned by \[user]admin\.\w+\[\/user]\. The warning is set to expire on \d+-\d+-\d+ \d+:\d+:\d+\. Remember, repeated warnings may jeopardize your account\./",
            $body,
            'warn-user-inbox-pm-body-start'
        );
        $this->assertStringEndsWith($message . '[/quote]', $body, 'warn-user-inbox-pm-body-end');
    }

    public function testForumPoll(): void {
        $this->category = (new \Gazelle\Manager\ForumCategory())->create('phpunit category', 10002);
        $admin          = $this->userList['admin'];
        $user           = $this->userList['user'];
        $this->forum    = Helper::makeForum(
            user:           $admin,
            sequence:       151,
            category:       $this->category,
            name:           'phpunit poll forum',
            description:    'This is where it polls',
        );

        $this->threadList[] = $thread
            = (new \Gazelle\Manager\ForumThread())->create($this->forum, $user, 'phpunit post pin', 'this is a new thread for post pins');

        $answer  = ['apple', 'banana', 'carrot'];
        $pollMan = new \Gazelle\Manager\ForumPoll();
        $poll    = $pollMan->create($thread->id(), 'Best food', $answer);
        $this->assertInstanceOf(\Gazelle\ForumPoll::class, $poll, 'forum-poll-is-forum-poll');
        $this->assertFalse($poll->isClosed(), 'forum-poll-is-not-closed');
        $this->assertFalse($poll->hasRevealVotes(), 'forum-poll-is-not-featured');
        $this->assertFalse($poll->isFeatured(), 'forum-poll-is-not-featured');
        $this->assertEquals(0, $poll->total(), 'forum-poll-total');
        $this->assertEquals($thread->id(), $poll->thread()->id(), 'forum-poll-thread-id');
        $this->assertCount(3, $poll->vote(), 'forum-poll-vote-count');
        $this->assertEquals($answer[1], $poll->vote()[1]['answer'], 'forum-poll-vote-1');

        $find = $pollMan->findById($poll->id());
        $this->assertEquals($poll->id(), $find->id(), 'forum-poll-find-by-id');

        $this->assertEquals(1, $poll->addAnswer('sushi'), 'forum-poll-add-answer');

        $this->assertEquals(0, $poll->addVote($user, 12345), 'forum-poll-bad-vote');
        $this->assertEquals(1, $poll->addVote($user, 2),     'forum-poll-good-vote');
        $this->assertEquals(2, $poll->response($user),       'forum-poll-response-before');
        $this->assertEquals(0, $poll->addVote($user, 3),     'forum-poll-revote');
        $this->assertEquals(1, $poll->modifyVote($user, 3),  'forum-poll-change-vote');
        $this->assertEquals(3, $poll->response($user),       'forum-poll-response-after');

        $poll->setField('Featured', date('Y-m-d H-i-s'))->modify();
        $this->assertTrue($poll->isFeatured(), 'forum-poll-is-featured');

        $poll->close()->setField('Featured', null)->modify();
        $this->assertTrue($poll->isClosed(), 'forum-poll-is-closed');
        $this->assertFalse($poll->isFeatured(), 'forum-poll-is-no-longer-featured');

        $poll->setField('Closed', '0')->setField('Featured', date('Y-m-d H-i-s'))->modify();
        $this->assertFalse($poll->isClosed(), 'forum-poll-is-reopened');
        $this->assertTrue($poll->isFeatured(), 'forum-poll-is-refeatured');
    }

    public function testPostPin(): void {
        $this->category = (new \Gazelle\Manager\ForumCategory())->create('phpunit category', 10002);
        $admin          = $this->userList['admin'];
        $user           = $this->userList['user'];
        $this->forum    = Helper::makeForum(
            user:           $admin,
            sequence:       151,
            category:       $this->category,
            name:           'phpunit pin forum',
            description:    'This is where it pins',
        );

        $this->threadList[] = $thread
            = (new \Gazelle\Manager\ForumThread())->create($this->forum, $user, 'unittest post pin', 'this is a new thread for post pins');
        $post = $thread->addPost($user, 'pinnable content');

        $this->assertFalse($post->isPinned(), 'forum-post-is-not-pinned');
        $this->assertEquals(1, $post->pin($admin, true), 'forum-post-pin');
        $this->assertTrue($post->isPinned(), 'forum-post-is-now-pinned');
        $this->assertEquals(1, $post->pin($admin, false), 'forum-post-unpin');
        $this->assertFalse($post->isPinned(), 'forum-post-is-no-longer-pinned');
    }

    public function testForumRender(): void {
        $name = 'phpunit category ' . randomString(6);
        $this->category = (new \Gazelle\Manager\ForumCategory())->create($name, 10002);
        $admin          = $this->userList['admin'];
        $this->forum    = Helper::makeForum(
            user:           $admin,
            sequence:       153,
            category:       $this->category,
            name:           'phpunit render forum',
            description:    'This is where it renders',
        );
        $paginator = (new Gazelle\Util\Paginator(TOPICS_PER_PAGE, 1))->setTotal(1);
        Gazelle\Base::setRequestContext(new Gazelle\BaseRequestContext('/forum.php', '127.0.0.1', ''));
        global $SessionID; // to render header()
        $SessionID = 'phpunit';
        global $Viewer;
        $Viewer = $admin;
        $this->assertStringContainsString(
            "<a href=\"forums.php#$name\">$name</a>",
            (Gazelle\Util\Twig::factory())->render('forum/forum.twig', [
                'dept_list'   => $this->forum->departmentList($admin),
                'donor_forum' => false,
                'forum'       => $this->forum,
                'toc'         => $this->forum->tableOfContentsForum($paginator->page()),
                'paginator'   => $paginator,
                'viewer'      => $admin,
            ]),
            'forum-render-forum',
        );
    }

    public function testEditPost(): void {
        $this->category = (new \Gazelle\Manager\ForumCategory())->create('phpunit category', 10011);
        $user = $this->userList['user'];
        $this->forum = Helper::makeForum(
            user:           $user,
            sequence:       154,
            category:       $this->category,
            name:           'phpunit forum twig',
            description:    'This is where it twigs',
        );
        $manager = new \Gazelle\Manager\ForumThread();
        $thread = $manager->create($this->forum, $user, 'thread title', 'this is a new thread');
        $this->assertEquals(1, $thread->postTotalSummary(), 'fthread-post-total-summary');
        $slice = $thread->slice(1, 1);
        $post = (new Gazelle\Manager\ForumPost())->findById($slice[0]['ID']);
        $this->assertEquals($thread->body(), $post->body(), 'thread-initial-body');
        $post->setField('Body', 'edit')->modify();
        // flush thread object to pick up out-of-band modification
        $this->assertEquals('edit', $thread->flush()->body(), 'thread-edit-body');
        $this->assertEquals($post->created(), $thread->lastPostTime(), 'thread-last-post-date');

        $this->assertEquals(1, $thread->mergePost($post, $user, 'merge this'), 'thread-merge-post');
        $newBody = "edit\n\nmerge this";
        $this->assertEquals($newBody, $post->body());
        $this->assertEquals(1, $thread->postTotalSummary(), 'fthread-merge-post-total-summary');

        $slice = $thread->slice(1, 1);
        $this->assertEquals($newBody, $slice[0]['Body'], 'thread-merge-post-slice');
        $merged = (new Gazelle\Manager\ForumPost())->findById($slice[0]['ID']);
        $this->assertEquals($newBody, $merged->body(), 'thread-merged-body');

        $post = $thread->addPost($user, 'second');
        $this->assertEquals(2, $thread->postTotalSummary(), 'fthread-merge-post-add-summary');
        $this->assertEquals(1, $thread->mergePost($post, $user, 'merge more'), 'thread-merge-second-post');
        $this->assertEquals(2, $thread->postTotalSummary(), 'fthread-merge-post-add-merge-summary');
        $this->assertEquals($post->created(), $thread->lastPostTime(), 'thread-merge-last-post-date');
    }

    public function testForumTransition(): void {
        $admin = $this->userList['admin'];
        $user  = $this->userList['user'];
        $this->category = (new \Gazelle\Manager\ForumCategory())->create('phpunit forum transition', 10005);
        $this->forum = Helper::makeForum(
            user:           $admin,
            sequence:       153,
            category:       $this->category,
            name:           'phpunit forum transition a',
            description:    'This is where it transitions',
        );
        $threadMan = new \Gazelle\Manager\ForumThread();
        $thread    = $threadMan->create($this->forum, $admin, 'thread title normal transition', 'this is a new thread normal transition');
        $pinned    = $threadMan->create($this->forum, $admin, 'thread title pinned transition', 'this is a new thread pinned transition');
        $locked    = $threadMan->create($this->forum, $admin, 'thread title locked transition', 'this is a new thread locked transition');

        $this->assertEquals(1, $pinned->editThread($pinned->forum(), true, 10, false, $pinned->title()), 'fthread-edit-pin');
        $this->assertEquals(1, $locked->editThread($locked->forum(), false, 0, true, $locked->title()), 'fthread-edit-lock');
        $this->assertTrue($locked->isLocked(), 'fthread-is-locked');
        $this->assertTrue($pinned->isPinned(), 'fthread-is-pinned');

        $this->extra = Helper::makeForum(
            user:           $admin,
            sequence:       153,
            category:       $this->category,
            name:           'phpunit forum transition b',
            description:    'This is where it also transitions',
        );
        $manager = new Gazelle\Manager\ForumTransition();
        $transition = $manager->create(
           source:           $this->forum,
           destination:      $this->extra,
           label:            'phpunit',
           userClass:        $this->userList['admin']->classLevel(),
           secondaryClasses: '',
           privileges:       '',
           userIds:          '',
        );
        $this->assertInstanceOf(\Gazelle\ForumTransition::class, $transition, 'forum-trans-create');
        $this->assertEquals('phpunit', $transition->label(), 'forum-trans-label');
        $this->assertEquals($this->forum->id(), $transition->sourceId(), 'forum-trans-source');
        $this->assertEquals($this->extra->id(), $transition->destinationId(), 'forum-trans-dest');
        $this->assertEquals($this->userList['admin']->classLevel(), $transition->classLevel(), 'forum-trans-class-level');
        $this->assertCount(0, $transition->secondaryClassIdList(), 'forum-trans-empty-secondary');
        $this->assertCount(0, $transition->userIdList(), 'forum-trans-empty-user-list');
        $this->assertTrue($transition->hasUserForThread($this->userList['admin'], $thread), 'forum-trans-has-admin');
        $this->assertFalse($transition->hasUserForThread($this->userList['user'], $thread), 'forum-trans-hasnt-user');
        $this->assertEquals(1, $transition->remove(), 'forum-trans-remove');

        $this->userList['FLS'] = Helper::makeUser('fls.' . randomString(10), 'forum');
        $this->userList['specific'] = Helper::makeUser('spec.' . randomString(10), 'forum');
        $this->transitionList[] = $manager->create(
           source:           $this->forum,
           destination:      $this->extra,
           label:            'phpunit',
           userClass:        $this->userList['admin']->classLevel(),
           secondaryClasses: (string)FLS_TEAM,
           privileges:       '',
           userIds:          (string)$this->userList['specific']->id(),
        );
        (new Gazelle\User\Privilege($this->userList['FLS']))->addSecondaryClass(FLS_TEAM);
        $this->assertTrue($this->userList['FLS']->isFLS(), 'user-is-fls');
        $this->assertTrue($this->transitionList[0]->hasUserForThread($this->userList['FLS'], $thread), 'forum-trans-has-fls');
        $this->assertFalse($this->transitionList[0]->hasUserForThread($this->userList['FLS'], $pinned), 'forum-trans-fls-no-pinned');
        $this->assertFalse($this->transitionList[0]->hasUserForThread($this->userList['FLS'], $locked), 'forum-trans-fls-no-locked');
        $this->assertTrue($this->transitionList[0]->hasUserForThread($this->userList['specific'], $thread), 'forum-trans-has-specific');
        $this->assertFalse($this->transitionList[0]->hasUserForThread($this->userList['specific'], $pinned), 'forum-trans-specific-no-pinned');
        $this->assertFalse($this->transitionList[0]->hasUserForThread($this->userList['specific'], $locked), 'forum-trans-specific-no-locked');

        $this->assertCount(
            1,
            $manager->userTransitionList($this->userList['specific']),
            'forum-user-transition-list'
        );
        $list = $manager->threadTransitionList($this->userList['FLS'], $thread);
        $this->assertCount(1, $list, 'thread-transition-list');
        $this->assertEquals(
            $this->transitionList[0]->id(),
            $list[$this->transitionList[0]->id()]->id(),
            'forum-thread-transition-list'
        );

        $this->assertCount(0, $manager->threadTransitionList($this->userList['FLS'], $pinned), 'forum-trans-thread-fls-pinned');
        $this->assertCount(0, $manager->threadTransitionList($this->userList['FLS'], $locked), 'forum-trans-thread-fls-locked');
        $this->assertCount(0, $manager->threadTransitionList($this->userList['specific'], $pinned), 'forum-trans-thread-specific-pinned');
        $this->assertCount(0, $manager->threadTransitionList($this->userList['specific'], $locked), 'forum-trans-thread-specific-locked');
    }

    public function testForumTwig(): void {
        $this->category = (new \Gazelle\Manager\ForumCategory())->create('phpunit category', 10011);
        $user = $this->userList['user'];
        $this->forum = Helper::makeForum(
            user:           $user,
            sequence:       154,
            category:       $this->category,
            name:           'phpunit forum twig',
            description:    'This is where it twigs',
        );
        $thread = (new \Gazelle\Manager\ForumThread())->create($this->forum, $user, 'thread title', 'this is a new thread');

        $template = Gazelle\Util\Twig::factory()->createTemplate(
            "{% if object is forum_thread %}yes{% else %}no{% endif %}"
        );
        $this->assertEquals('yes', $template->render(['object' => $thread]), 'forum-twig-thread');
        $this->assertEquals('no', $template->render(['object' => $this->forum]), 'forum-twig-forum');
    }
}
