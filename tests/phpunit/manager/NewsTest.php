<?php

namespace Gazelle;

use PHPUnit\Framework\TestCase;

class NewsTest extends TestCase {
    protected array $userList;
    protected int   $news;
    protected Forum $forum;

    public function setUp(): void {
        $this->userList = [
            \GazelleUnitTest\Helper::makeUser('news.' . randomString(10), 'news'),
            \GazelleUnitTest\Helper::makeUser('news.' . randomString(10), 'news'),
        ];
        $this->forum = (new Manager\Forum())->findById(ANNOUNCEMENT_FORUM_ID);
    }

    public function tearDown(): void {
        if (isset($this->news)) {
            (new Manager\News())->remove($this->news);
        }
        foreach ($this->userList as $user) {
            // note: removing a user removes their forum threads
            $user->remove();
        }
    }

    public function testNewsCreate(): void {
        $manager    = new Manager\News();
        $threadMan  = new Manager\ForumThread();
        $body       = 'phpunit news body';
        $title      = 'phpunit news ' . randomString(10);
        $initial    = $manager->headlines();
        $this->news = $manager->create(
            $this->userList[0],
            $title,
            $body,
            'phpunit discuss me',
            $this->forum,
            $threadMan,
        );
        $thread = $threadMan->findById($this->forum->lastThreadId());
        $this->assertInstanceOf(ForumThread::class, $thread, 'news-announcement-thread');
        $this->assertEquals($this->userList[0]->id(), $thread->authorId(), 'news-thread-author-id');
        $this->assertEquals($body, $thread->body(), 'news-thread-body');
        $this->assertEquals($title, $thread->title(), 'news-thread-title');

        $this->assertEquals(1 + count($initial), count($manager->headlines()), 'news-headlines');
        $this->assertEquals($this->news, $manager->latestId(), 'news-id-latest');

        $this->assertEquals(1, $manager->remove($this->news), 'news-remove');
        unset($this->news);
    }

    public function testNewsWitness(): void {
        $manager    = new Manager\News();
        $this->news = $manager->create(
            $this->userList[0],
            'phpunit news witness',
            'phpunit news witness body',
            'phpunit news discuss',
            $this->forum,
            new Manager\ForumThread(),
        );

        $witness = new WitnessTable\UserReadNews();
        $this->assertNull($witness->lastRead($this->userList[1]), 'news-user-not-read');
        $this->assertTrue($witness->witness($this->userList[1]));
        $this->assertEquals($this->news, $witness->lastRead($this->userList[1]), 'news-user-read');
    }

    public function testNewsNotification(): void {
        $manager    = new Manager\News();
        $title      = 'phpunit news notif';
        $this->news = $manager->create(
            $this->userList[0],
            $title,
            'phpunit news notif body',
            'phpunit notif discuss',
            $this->forum,
            new Manager\ForumThread(),
        );

        $notifier = new User\Notification($this->userList[1]);
        // if this fails, the CI database has drifted (or another UT has clobbered the expected value here)
        $this->assertTrue($notifier->isActive('News'), 'activity-notified-news');

        $alertList = $notifier->setDocument('index.php', '')->alertList();
        $this->assertArrayHasKey('News', $alertList, 'alert-has-news');

        $alertNews = $alertList['News'];
        $this->assertInstanceOf(User\Notification\News::class, $alertNews, 'alert-news-instance');
        $this->assertEquals('News', $alertNews->type(), 'alert-news-type');
        $this->assertEquals("Announcement: $title", $alertNews->title(), 'alert-news-title');
        $this->assertEquals($this->news, $alertNews->context(), 'alert-news-context-is-news');
    }
}
