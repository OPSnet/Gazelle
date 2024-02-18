<?php

use PHPUnit\Framework\TestCase;

require_once(__DIR__ . '/../../../lib/bootstrap.php');
require_once(__DIR__ . '/../../helper.php');

class NewsTest extends TestCase {
    protected array $userList;
    protected int $news;

    public function setUp(): void {
        $this->userList = [
            Helper::makeUser('news.' . randomString(10), 'news'),
            Helper::makeUser('news.' . randomString(10), 'news'),
        ];
    }

    public function tearDown(): void {
        if (isset($this->news)) {
            (new \Gazelle\Manager\News())->remove($this->news);
        }
        foreach ($this->userList as $user) {
            $user->remove();
        }
    }

    public function testNewsCreate(): void {
        $manager = new \Gazelle\Manager\News();
        $initial = $manager->headlines();
        $this->news = $manager->create(
            $this->userList[0],
            'phpunit news',
            'phpunit news body',
        );

        $this->assertEquals(1 + count($initial), count($manager->headlines()), 'news-headlines');
        $this->assertEquals($this->news, $manager->latestId(), 'news-id-latest');

        $this->assertEquals(1, $manager->remove($this->news), 'news-remove');
        unset($this->news);
    }

    public function testNewsWitness(): void {
        $manager    = new \Gazelle\Manager\News();
        $this->news = $manager->create(
            $this->userList[0],
            'phpunit news witness',
            'phpunit news witness body',
        );

        $witness = new \Gazelle\WitnessTable\UserReadNews();
        $this->assertNull($witness->lastRead($this->userList[1]), 'news-user-not-read');
        $this->assertTrue($witness->witness($this->userList[1]));
        $this->assertEquals($this->news, $witness->lastRead($this->userList[1]), 'news-user-read');
    }

    public function testNewsNotification(): void {
        $manager    = new \Gazelle\Manager\News();
        $title      = 'phpunit news notif';
        $this->news = $manager->create(
            $this->userList[0],
            $title,
            'phpunit news notif body',
        );

        $notifier = new Gazelle\User\Notification($this->userList[1]);
        // if this fails, the CI database has drifted (or another UT has clobbered the expected value here)
        $this->assertTrue($notifier->isActive('News'), 'activity-notified-news');

        $alertList = $notifier->setDocument('index.php', '')->alertList();
        $this->assertArrayHasKey('News', $alertList, 'alert-has-news');

        $alertNews = $alertList['News'];
        $this->assertInstanceOf(Gazelle\User\Notification\News::class, $alertNews, 'alert-news-instance');
        $this->assertEquals('News', $alertNews->type(), 'alert-news-type');
        $this->assertEquals("Announcement: $title", $alertNews->title(), 'alert-news-title');
        $this->assertEquals($this->news, $alertNews->context(), 'alert-news-context-is-news');
    }
}
