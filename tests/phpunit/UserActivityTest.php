<?php

use \PHPUnit\Framework\TestCase;

require_once(__DIR__ . '/../../lib/bootstrap.php');

class UserActivityTest extends TestCase {
    protected Gazelle\Manager\User $userMan;

    public function setUp(): void {
        $this->userMan = new Gazelle\Manager\User;
    }

    public function tearDown(): void {}

    public function testActivity() {
        $admin = $this->userMan->find('@admin');
        $activity = new Gazelle\User\Activity($admin);
        $this->assertInstanceOf(Gazelle\User\Activity::class, $activity, 'user-activity-instance');

        $this->assertCount(0, $activity->actionList(), 'user-activity-action');
        $activity->setAlert('alert1')->setAlert('alert2');
        $this->assertCount(2, $activity->alertList(), 'user-activity-alert');

        $this->assertInstanceOf(Gazelle\User\Activity::class, $activity->configure(), 'user-activity-configure');
        $this->assertInstanceOf(Gazelle\User\Activity::class, $activity->setApplicant(new Gazelle\Manager\Applicant), 'user-activity-applicant');
        $this->assertInstanceOf(Gazelle\User\Activity::class, $activity->setDb(new Gazelle\DB), 'user-activity-db');
        $this->assertInstanceOf(Gazelle\User\Activity::class, $activity->setPayment(new Gazelle\Manager\Payment), 'user-activity-payment');
        $this->assertInstanceOf(Gazelle\User\Activity::class, $activity->setReferral(new Gazelle\Manager\Referral), 'user-activity-referral');
        $this->assertInstanceOf(Gazelle\User\Activity::class, $activity->setReport(new Gazelle\Stats\Report), 'user-activity-report');
        $this->assertInstanceOf(Gazelle\User\Activity::class, $activity->setSSLHost(new Gazelle\Manager\SSLHost), 'user-activity-sslhost');
        $this->assertInstanceOf(Gazelle\User\Activity::class, $activity->setScheduler(new Gazelle\Schedule\Scheduler), 'user-activity-scheduler');
        $this->assertInstanceOf(Gazelle\User\Activity::class, $activity->setStaff(new Gazelle\Staff($admin)), 'user-activity-staff-set');
        $this->assertInstanceOf(Gazelle\User\Activity::class, $activity->setStaffPM(new Gazelle\Manager\StaffPM), 'user-activity-staffpm');
    }

    public function testBlog() {
        $manager = new Gazelle\Manager\Blog;
        $title   = 'Unit test blog';
        $body    = 'Blogging about unit tests';
        $blog = $manager->create([
            'userId'    => 1,
            'title'     => $title,
            'body'      => $body,
            'threadId'  => null,
            'important' => 1,
        ]);
        $this->assertInstanceOf(Gazelle\Blog::class, $blog, 'activity-blog-create');
        $this->assertEquals($body,  $blog->body(),      'blog-body');
        $this->assertEquals($title, $blog->title(),     'blog-title');
        $this->assertEquals(1,      $blog->important(), 'blog-importance');
        $this->assertEquals(0,      $blog->threadId(),  'blog-thread-id');
        $this->assertEquals(1,      $blog->userId(),    'blog-user-id');
        $this->assertEquals($blog->id(), $manager->latestId(), 'blog-id-is-latest');

        $notifier = new Gazelle\User\Notification($this->userMan->find('@user'));
        // if this fails, the CI database has drifted (or another UT has clobbered the expected value here)
        $this->assertTrue($notifier->isActive('Blog'), 'activity-notified-blog');

        $alertList = $notifier->setDocument('index.php', '')->alertList();
        $this->assertArrayHasKey('Blog', $alertList, 'alert-has-blog');

        $alertBlog = $alertList['Blog'];
        $this->assertInstanceOf(Gazelle\User\Notification\Blog::class, $alertBlog, 'alert-blog-instance');
        $this->assertEquals('Blog', $alertBlog->type(), 'alert-blog-type');
        $this->assertEquals("Blog: $title", $alertBlog->title(), 'alert-blog-title');
        $this->assertEquals($blog->id(), $alertBlog->context(), 'alert-blog-context-is-blog');
        $this->assertEquals($blog->url(), $alertBlog->url(), 'alert-blog-url-is-blog');

        $this->assertEquals(1, $blog->remove(), 'blog-remove');
    }

    public function testGlobal() {
        $global = new Gazelle\Notification\GlobalNotification;
        $global->remove(); // just in case

        $title  = 'global 10 minute news';
        $this->assertTrue($global->create($title, '', 'information', 10), 'global-create');
        $this->assertIsArray($global->alert(), 'global-alert');
        $this->assertEqualsWithDelta(10 * 60, $global->remaining(), 30, 'global-remaining-within-30sec');

        $notifier = new Gazelle\User\Notification($this->userMan->find('@user'));
        $alertList = $notifier->alertList();
        $this->assertArrayHasKey('Global', $alertList, 'alert-has-global');

        $alertGlobal = $alertList['Global'];
        $this->assertInstanceOf(Gazelle\User\Notification\GlobalNotification::class, $alertGlobal, 'alert-global-instance');
        $this->assertEquals($title, $alertGlobal->title(), 'alert-global-title');
        $this->assertEquals(1, $alertGlobal->clear(), 'alert-global-clear');

        // tidy up
        $global->remove();
    }

    public function testInbox() {
        $admin = $this->userMan->find('@admin');
        $user  = $this->userMan->find('@user');

        $inbox = new Gazelle\User\Notification\Inbox($user);
        $this->assertInstanceOf(Gazelle\User\Notification\Inbox::class, $inbox, 'alert-notification-inbox-instance');
        if ($inbox->load()) {
            // there are some messages in the inbox, mark them as read
            $inbox->clear();
        }

        // send a message
        $convId = $this->userMan->sendPM($user->id(), $admin->id(), 'unit test message', 'unit test body');
        $this->assertGreaterThan(0, $convId, 'alert-inbox-send');

        // check out the notifications
        $notifier = new Gazelle\User\Notification($user);
        // if this fails, the CI database has drifted (or another UT has clobbered the expected value here)
        $this->assertTrue($notifier->isActive('Inbox'), 'activity-notified-inbox');

        // see the new message
        $alertList = $notifier->alertList();
        $this->assertArrayHasKey('Inbox', $alertList, 'alert-has-inbox');
        $alertInbox = $alertList['Inbox'];
        $this->assertInstanceOf(Gazelle\User\Notification\Inbox::class, $alertInbox, 'alert-inbox-instance');
        $this->assertEquals('You have a new message', $alertInbox->title(), 'alert-inbox-unread');

        // read it
        $pm = (new Gazelle\Manager\PM($user))->findById($convId);
        $this->assertInstanceOf(Gazelle\PM::class, $pm, 'inbox-unread-pm');
        $this->assertEquals(1, $pm->markRead(), 'alert-pm-read');
    }

    public function testNews() {
        $admin = $this->userMan->find('@admin');
        $user  = $this->userMan->find('@user');
        $title = "This is the 6 o'clock news";

        $manager = new Gazelle\Manager\News;
        $newsId  = $manager->create($admin->id(), $title, 'Not much happened');
        $this->assertGreaterThan(0, $newsId, 'alert-news-create');
        $this->assertNull($manager->fetch(-1), 'alert-no-news-is-null-news');
        $info = $manager->fetch($newsId);
        $this->assertCount(2, $info, 'alert-latest-news');

        $notifier = new Gazelle\User\Notification($user);
        // if this fails, the CI database has drifted (or another UT has clobbered the expected value here)
        $this->assertTrue($notifier->isActive('News'), 'activity-notified-news');

        $alertList = $notifier->alertList();
        $this->assertArrayHasKey('News', $alertList, 'alert-has-news');
        $alertNews = $alertList['News'];
        $this->assertInstanceOf(Gazelle\User\Notification\News::class, $alertNews, 'alert-news-instance');
        $this->assertEquals("Announcement: $title", $alertNews->title(), 'alert-news-unread');

        $this->assertEquals(1, $manager->remove($newsId), 'alert-news-remove');
        $this->assertEquals(0, $manager->remove($newsId), 'alert-news-twice-removed');
    }
}
