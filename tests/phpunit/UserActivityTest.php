<?php

namespace Gazelle;

use PHPUnit\Framework\TestCase;
use GazelleUnitTest\Helper;
use Gazelle\Enum\UserStatus;

class UserActivityTest extends TestCase {
    protected array $userList;

    public function tearDown(): void {
        foreach ($this->userList as $user) {
            $user->remove();
        }
    }

    public function testActivityBasic(): void {
        $this->userList['admin'] = Helper::makeUser('admin.' . randomString(10), 'activity');
        $this->userList['admin']->setField('PermissionID', SYSOP)
            ->setField('Enabled', UserStatus::enabled->value)
            ->modify();

        $activity = new User\Activity($this->userList['admin']);
        $this->assertCount(0, $activity->actionList(), 'user-activity-action');
        $activity->setAlert('alert1')->setAlert('alert2');
        $this->assertCount(2, $activity->alertList(), 'user-activity-alert');
    }

    public function testActivityConfigure(): void {
        // sanity checks + coverage
        $this->expectNotToPerformAssertions();
        $this->userList['admin'] = Helper::makeUser('admin.' . randomString(10), 'activity');
        $this->userList['admin']->setField('PermissionID', SYSOP)
            ->setField('Enabled', UserStatus::enabled->value)
            ->modify();
        $activity = new User\Activity($this->userList['admin']);
        $activity->configure();
        $activity->setApplicant(new Manager\Applicant());
        $activity->setPayment(new Manager\Payment());
        $activity->setReferral(new Manager\Referral());
        $activity->setReport(new Stats\Report());
        $activity->setSSLHost(new Manager\SSLHost());
        $activity->setScheduler(new TaskScheduler());
        $activity->setStaff($this->userList['admin']);
        $activity->setStaffPM(new Manager\StaffPM());
    }

    public function testGlobal(): void {
        $global = new Notification\GlobalNotification();
        $global->remove(); // just in case

        $title  = 'global 10 minute news';
        $this->assertTrue($global->create($title, '', 'information', 10), 'global-create');
        $this->assertIsArray($global->alert(), 'global-alert');
        $this->assertEqualsWithDelta(10 * 60, $global->remaining(), 30, 'global-remaining-within-30sec');

        $this->userList['user'] = Helper::makeUser('user.' . randomString(10), 'activity');
        $notifier = new User\Notification($this->userList['user']);
        $alertList = $notifier->alertList();
        $this->assertArrayHasKey('Global', $alertList, 'alert-has-global');

        $alertGlobal = $alertList['Global'];
        $this->assertInstanceOf(User\Notification\GlobalNotification::class, $alertGlobal, 'alert-global-instance');
        $this->assertSame($title, $alertGlobal->title(), 'alert-global-title');
        $this->assertSame(1, $alertGlobal->clear(), 'alert-global-clear');

        // tidy up
        $global->remove();
    }

    public function testInbox(): void {
        $this->userList['admin'] = Helper::makeUser('admin.' . randomString(10), 'activity');
        $this->userList['admin']->setField('PermissionID', SYSOP)->modify();
        $this->userList['user'] = Helper::makeUser('user.' . randomString(10), 'activity');

        $admin = $this->userList['admin'];
        $user  = $this->userList['user'];
        $notification = new User\Notification\Inbox($user);
        $this->assertFalse($notification->load(), 'alert-notification-inbox-none');
        $this->assertSame(0, $notification->clear(), 'alert-notification-inbox-clear');

        // send a message from admin to user
        $pm = $user->inbox()->create($admin, 'unit test message', 'unit test body');
        $this->assertInstanceOf(PM::class, $pm, 'alert-inbox-send');

        // check out the notifications
        $notifier = new User\Notification($user);
        // if this fails, the CI database has drifted (or another UT has clobbered the expected value here)
        $this->assertTrue($notifier->isActive('Inbox'), 'activity-notified-inbox');

        // see the new message
        $alertList = $notifier->alertList();
        $this->assertArrayHasKey('Inbox', $alertList, 'alert-has-inbox');
        $alertInbox = $alertList['Inbox'];
        $this->assertInstanceOf(User\Notification\Inbox::class, $alertInbox, 'alert-inbox-instance');
        $this->assertSame('You have a new message', $alertInbox->title(), 'alert-inbox-unread');

        // read it
        $read = new Manager\PM($user)->findById($pm->id);
        $this->assertInstanceOf(PM::class, $read, 'inbox-unread-pm');
        $this->assertSame(1, $read->markRead(), 'alert-pm-read');
    }

    public function testNews(): void {
        $this->userList['admin'] = Helper::makeUser('admin.' . randomString(10), 'activity');
        $this->userList['admin']->setField('PermissionID', SYSOP)->modify();
        $this->userList['user'] = Helper::makeUser('user.' . randomString(10), 'activity');

        $manager = new Manager\News();
        $title   = "This is the 6 o'clock news";
        $newsId  = $manager->create(
            $title,
            'Not much happened',
            'Discuss nothing',
            $this->userList['admin'],
            new Manager\Forum()->findById(ANNOUNCEMENT_FORUM_ID),
        );
        $this->assertGreaterThan(0, $newsId, 'alert-news-create');
        $this->assertNull($manager->fetch(-1), 'alert-no-news-is-null-news');
        $info = $manager->fetch($newsId);
        $this->assertIsArray($info, 'alert-latest-news-array');
        $this->assertCount(2, $info, 'alert-latest-news-count');

        $notifier = new User\Notification($this->userList['user']);
        // if this fails, the CI database has drifted (or another UT has clobbered the expected value here)
        $this->assertTrue($notifier->isActive('News'), 'activity-notified-news');

        $alertList = $notifier->alertList();
        $this->assertArrayHasKey('News', $alertList, 'alert-has-news');
        $alertNews = $alertList['News'];
        $this->assertInstanceOf(User\Notification\News::class, $alertNews, 'alert-news-instance');
        $this->assertSame("Announcement: $title", $alertNews->title(), 'alert-news-unread');

        $this->assertSame(1, $manager->remove($newsId), 'alert-news-remove');
        $this->assertSame(0, $manager->remove($newsId), 'alert-news-twice-removed');
    }
}
