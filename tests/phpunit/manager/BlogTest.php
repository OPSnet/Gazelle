<?php

use PHPUnit\Framework\TestCase;

class BlogTest extends TestCase {
    protected array $userList;
    protected \Gazelle\Blog $blog;

    public function setUp(): void {
        $this->userList = [
            Helper::makeUser('blog.' . randomString(10), 'blog'),
            Helper::makeUser('blog.' . randomString(10), 'blog'),
        ];
    }

    public function tearDown(): void {
        if (isset($this->blog)) {
            $this->blog->remove();
        }
        $db = Gazelle\DB::DB();
        foreach ($this->userList as $user) {
            $user->remove();
        }
    }

    public function testBlogCreate(): void {
        $manager = new \Gazelle\Manager\Blog();
        $initial = $manager->headlines();
        $this->blog = $manager->create([
            'userId'    => $this->userList[0]->id(),
            'title'     => 'phpunit blog',
            'body'      => 'phpunit blog body',
            'threadId'  => 0,
            'important' => 1,
        ]);
        $this->assertTrue(Helper::recentDate($this->blog->created()), 'blog-created');
        $this->assertEquals('phpunit blog body', $this->blog->body(), 'blog-body');
        $this->assertEquals(1, $this->blog->important(), 'blog-important');
        $this->assertEquals(0, $this->blog->threadId(), 'blog-thread-id');
        $this->assertEquals('phpunit blog', $this->blog->title(), 'blog-title');
        $this->assertEquals($this->userList[0]->id(), $this->blog->userId(), 'blog-userId');

        $this->assertEquals(1 + count($initial), count($manager->headlines()), 'blog-headlines');
        $this->assertEquals($this->blog->id(), $manager->latest()->id(), 'blog-latest');
        $this->assertEquals($this->blog->id(), $manager->latestId(), 'blog-id-latest');
        $find = $manager->findById($this->blog->id());
        $this->assertEquals($this->blog->id(), $find->id(), 'blog-find');
        $this->assertEquals((int)strtotime($find->created()), $manager->latestEpoch(), 'blog-epoch');

        $this->assertInstanceOf(\Gazelle\Blog::class, $this->blog->flush(), 'blog-flush');
        $this->assertEquals(1, $this->blog->remove(), 'blog-remove');
        unset($this->blog);
    }

    public function testBlogWitness(): void {
        $manager = new \Gazelle\Manager\Blog();
        $this->blog = $manager->create([
            'userId'    => $this->userList[0]->id(),
            'title'     => 'phpunit blog witness',
            'body'      => 'phpunit blog witness body',
            'threadId'  => 0,
            'important' => 1,
        ]);

        $witness = new \Gazelle\WitnessTable\UserReadBlog();
        $this->assertNull($witness->lastRead($this->userList[1]), 'blog-user-not-read');
        $this->assertTrue($witness->witness($this->userList[1]));
        $this->assertEquals($this->blog->id(), $witness->lastRead($this->userList[1]), 'blog-user-read');
    }

    public function testBlogNotification(): void {
        $manager = new \Gazelle\Manager\Blog();
        $title   = 'phpunit blog notif';
        $this->blog    = $manager->create([
            'userId'    => $this->userList[0]->id(),
            'title'     => $title,
            'body'      => 'phpunit blog notif body',
            'threadId'  => 0,
            'important' => 1,
        ]);

        $notifier = new Gazelle\User\Notification($this->userList[1]);
        // if this fails, the CI database has drifted (or another UT has clobbered the expected value here)
        $this->assertTrue($notifier->isActive('Blog'), 'activity-notified-blog');

        $alertList = $notifier->setDocument('index.php', '')->alertList();
        $this->assertArrayHasKey('Blog', $alertList, 'alert-has-blog');

        $alertBlog = $alertList['Blog'];
        $this->assertInstanceOf(Gazelle\User\Notification\Blog::class, $alertBlog, 'alert-blog-instance');
        $this->assertEquals('Blog', $alertBlog->type(), 'alert-blog-type');
        $this->assertEquals("Blog: $title", $alertBlog->title(), 'alert-blog-title');
        $this->assertEquals($this->blog->id(), $alertBlog->context(), 'alert-blog-context-is-blog');
        $this->assertEquals($this->blog->url(), $alertBlog->notificationUrl(), 'alert-blog-url-is-blog');
    }
}
