<?php

use PHPUnit\Framework\TestCase;
use Gazelle\Enum\UserStatus;

require_once(__DIR__ . '/../../lib/bootstrap.php');
require_once(__DIR__ . '/../helper.php');

class UserActivityTest extends TestCase {
    protected array $userList;

    public function tearDown(): void {
        foreach ($this->userList as $user) {
            $user->remove();
        }
    }

    public function testActivity(): void {
        $this->userList['admin'] = Helper::makeUser('admin.' . randomString(10), 'activity');
        $this->userList['admin']->setField('PermissionID', SYSOP)
            ->setField('Enabled', UserStatus::enabled->value)
            ->modify();

        $activity = new Gazelle\User\Activity($this->userList['admin']);
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
        $this->assertInstanceOf(Gazelle\User\Activity::class, $activity->setScheduler(new Gazelle\TaskScheduler), 'user-activity-scheduler');
        $this->assertInstanceOf(Gazelle\User\Activity::class, $activity->setStaff(new Gazelle\Staff($this->userList['admin'])), 'user-activity-staff-set');
        $this->assertInstanceOf(Gazelle\User\Activity::class, $activity->setStaffPM(new Gazelle\Manager\StaffPM), 'user-activity-staffpm');
    }

    public function testBlog(): void {
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

        $this->userList['user'] = Helper::makeUser('user.' . randomString(10), 'activity');
        $notifier = new Gazelle\User\Notification($this->userList['user']);
        // if this fails, the CI database has drifted (or another UT has clobbered the expected value here)
        $this->assertTrue($notifier->isActive('Blog'), 'activity-notified-blog');

        $alertList = $notifier->setDocument('index.php', '')->alertList();
        $this->assertArrayHasKey('Blog', $alertList, 'alert-has-blog');

        $alertBlog = $alertList['Blog'];
        $this->assertInstanceOf(Gazelle\User\Notification\Blog::class, $alertBlog, 'alert-blog-instance');
        $this->assertEquals('Blog', $alertBlog->type(), 'alert-blog-type');
        $this->assertEquals("Blog: $title", $alertBlog->title(), 'alert-blog-title');
        $this->assertEquals($blog->id(), $alertBlog->context(), 'alert-blog-context-is-blog');
        $this->assertEquals($blog->url(), $alertBlog->notificationUrl(), 'alert-blog-url-is-blog');

        $this->assertEquals(1, $blog->remove(), 'blog-remove');
        $this->userList['user']->remove();
    }

    public function testGlobal(): void {
        $global = new Gazelle\Notification\GlobalNotification;
        $global->remove(); // just in case

        $title  = 'global 10 minute news';
        $this->assertTrue($global->create($title, '', 'information', 10), 'global-create');
        $this->assertIsArray($global->alert(), 'global-alert');
        $this->assertEqualsWithDelta(10 * 60, $global->remaining(), 30, 'global-remaining-within-30sec');

        $this->userList['user'] = Helper::makeUser('user.' . randomString(10), 'activity');
        $notifier = new Gazelle\User\Notification($this->userList['user']);
        $alertList = $notifier->alertList();
        $this->assertArrayHasKey('Global', $alertList, 'alert-has-global');

        $alertGlobal = $alertList['Global'];
        $this->assertInstanceOf(Gazelle\User\Notification\GlobalNotification::class, $alertGlobal, 'alert-global-instance');
        $this->assertEquals($title, $alertGlobal->title(), 'alert-global-title');
        $this->assertEquals(1, $alertGlobal->clear(), 'alert-global-clear');

        // tidy up
        $global->remove();
    }

    public function testInbox(): void {
        $userMan = new Gazelle\Manager\User;
        $this->userList['admin'] = Helper::makeUser('admin.' . randomString(10), 'activity');
        $this->userList['admin']->setField('PermissionID', SYSOP)->modify();
        $this->userList['user'] = Helper::makeUser('user.' . randomString(10), 'activity');

        $admin = $this->userList['admin'];
        $user  = $this->userList['user'];
        $notification = new Gazelle\User\Notification\Inbox($user);
        $this->assertInstanceOf(Gazelle\User\Notification\Inbox::class, $notification, 'alert-notification-inbox-instance');
        $this->assertFalse($notification->load(), 'alert-notification-inbox-none');
        $this->assertEquals(0, $notification->clear(), 'alert-notification-inbox-clear');

        // send a message from admin to user
        $pm = $user->inbox()->create($admin, 'unit test message', 'unit test body');
        $this->assertGreaterThan(0, $pm->id(), 'alert-inbox-send');

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
        $read = (new Gazelle\Manager\PM($user))->findById($pm->id());
        $this->assertInstanceOf(Gazelle\PM::class, $read, 'inbox-unread-pm');
        $this->assertEquals(1, $read->markRead(), 'alert-pm-read');
    }

    public function testNews(): void {
        $this->userList['admin'] = Helper::makeUser('admin.' . randomString(10), 'activity');
        $this->userList['admin']->setField('PermissionID', SYSOP)->modify();
        $this->userList['user'] = Helper::makeUser('user.' . randomString(10), 'activity');

        $manager = new Gazelle\Manager\News;
        $title = "This is the 6 o'clock news";
        $newsId  = $manager->create($this->userList['admin']->id(), $title, 'Not much happened');
        $this->assertGreaterThan(0, $newsId, 'alert-news-create');
        $this->assertNull($manager->fetch(-1), 'alert-no-news-is-null-news');
        $info = $manager->fetch($newsId);
        $this->assertCount(2, $info, 'alert-latest-news');

        $notifier = new Gazelle\User\Notification($this->userList['user']);
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
