<?php

namespace Gazelle;

use PHPUnit\Framework\TestCase;
use GazelleUnitTest\Helper;
use Gazelle\Enum\UserAuditEvent;
use Gazelle\Enum\UserStatus;

class UserManagerTest extends TestCase {
    use Pg;

    protected array $userList;
    protected Request $request;

    public function setUp(): void {
        $this->userList = [
            Helper::makeUser('um1.' . randomString(10), 'userman', enable: true, clearInbox: true),
            Helper::makeUser('um2.' . randomString(10), 'userman', enable: true, clearInbox: true),
            Helper::makeUser('um3.' . randomString(10), 'userman', enable: true, clearInbox: true),
        ];
    }

    public function tearDown(): void {
        if (isset($this->request)) {
            $this->request->remove();
        }
        foreach ($this->userList as $user) {
            $user->remove();
        }
    }

    public function testUserMagicFind(): void {
        $manager = new Manager\User();
        $user    = $this->userList[2];
        $this->assertEquals(
            $user->id,
            $manager->find($user->id)->id,
            'uman-find-id'
        );
        $this->assertEquals(
            $user->id,
            $manager->find('@' . $user->username())->id,
            'uman-find-username'
        );
        $this->assertNull(
            $manager->find($user->id + 1),
            'uman-find-no-id'
        );
        $this->assertNull(
            $manager->find('@' . $user->username() . $user->username()),
            'uman-find-no-username'
        );
        $this->assertNull(
            $manager->find($user->username()),
            'uman-find-no-at'
        );
    }

    public function testUserFindCustomPriv(): void {
        $user      = $this->userList[1];
        $privilege = 'site_view_torrent_snatchlist';
        $user->privilege()->modifyCustomList([$privilege => true]);
        $this->assertContains(
            $user->id,
            array_keys(new Manager\User()->findAllByCustomPermission()),
            'uman-find-all-custom'
        );
    }

    public function testUserEmailFind(): void {
        $manager = new Manager\User();
        $this->assertEquals(
            $this->userList[0]->id,
            $manager->findByEmail($this->userList[0]->email())->id,
            'uman-find-by-email'
        );
    }

    public function testCycleAuthKeys(): void {
        $this->assertEquals(
            (int)DB::DB()->scalar("
                SELECT count(*) FROM users_main
            "),
            new Manager\User()->cycleAuthKeys(),
            'uman-auth-keys'
        );
    }

    public function testDisableUserList(): void {
        $manager = new Manager\User();
        $idList  = array_map(fn ($u) => $u->id, $this->userList);

        $this->assertEquals(
            3,
            $manager->disableUserList(
                $idList,
                UserAuditEvent::activity,
                'phpunit mass disable',
                3
            ),
            'uman-mass-disable'
        );
        $this->userList[2]->flush();
        $this->assertTrue($this->userList[2]->isDisabled(), 'uman-disabled-user2');
        $this->assertFalse($this->userList[2]->isEnabled(), 'uman-enabled-user2');
        $this->assertFalse($this->userList[2]->isUnconfirmed(), 'uman-unconfirmed-user2');
    }

    public function testDisplayUsername(): void {
        $manager = new Manager\User();
        $this->userList[1]->privilege()->addSecondaryClass(FLS_TEAM);
        $this->userList[2]->setField('Enabled', UserStatus::disabled->value)->modify();

        $this->assertEquals(
            "<a href=\"user.php?id={$this->userList[1]->id}\">{$this->userList[1]->username()}</a>",
            $manager->displayUsername($this->userList[1]->id, $this->userList[0]),
            'uman-display-user-short'
        );
        $this->assertEquals(
            "<a class=\"username\" href=\"user.php?id={$this->userList[1]->id}\">{$this->userList[1]->username()}</a>&nbsp;<span class=\"tooltip secondary_class\" title=\"First Line Support\">FLS</span> (User)",
            $manager->displayUsername($this->userList[1]->id, $this->userList[0], true),
            'uman-display-user-secondary'
        );
        $this->assertEquals(
            "<a class=\"username\" href=\"user.php?id={$this->userList[2]->id}\">{$this->userList[2]->username()}</a><a href=\"rules.php\"><img loading=\"lazy\" class=\"tooltip\" src=\""
                . STATIC_SERVER
                . "/common/symbols/disabled.png\" alt=\"Banned\" title=\"Disabled\" /></a> (User)",
            $manager->displayUsername($this->userList[2]->id, $this->userList[0], true),
            'uman-display-user-disabled'
        );
        $this->assertEquals(
            "System",
            $manager->displayUsername(0, $this->userList[0]),
            'uman-display-system'
        );
        $this->assertEquals(
            "[Unknown]",
            $manager->displayUsername((int)$this->userList[2]->id + 100, $this->userList[0]),
            'uman-display-404'
        );
        $this->userList[2]->setField('PermissionID', SYSOP)->modify();
        $id404 = (int)$this->userList[2]->id + 100;
        $this->assertEquals(
            "[Unknown $id404]",
            $manager->displayUsername($id404, $this->userList[2]),
            'uman-display-staff-404'
        );
    }

    public function testModifyUserAttr(): void {
        $manager = new Manager\User();
        $idList  = array_map(fn ($u) => $u->id, $this->userList);
        $this->assertFalse($this->userList[0]->hasAttr('hide-tags'), 'uman-attr-no-attr');

        $this->assertEquals(3, $manager->modifyAttr($idList, 'hide-tags', true), 'uman-attr-modify');
        $this->assertTrue($this->userList[0]->flush()->hasAttr('hide-tags'), 'uman-attr-has-attr');
    }

    public function testModifyUserMassToken(): void {
        $manager = new Manager\User();
        array_map(fn ($u) => $u->id, $this->userList);
        $this->assertEquals(0, $this->userList[0]->tokenCount(), 'uman-masstoken-initial');

        $manager->addMassTokens(10, allowLeechDisabled: false);
        foreach ($this->userList as $user) {
            $user->flush();
        }
        $this->assertEquals(10, $this->userList[0]->tokenCount(), 'uman-masstoken-add-all-0');
        $this->assertEquals(10, $this->userList[1]->tokenCount(), 'uman-masstoken-add-all-1');
        $this->assertEquals(10, $this->userList[2]->tokenCount(), 'uman-masstoken-add-all-2');

        $this->userList[0]->setField('can_leech', 0)->modify();
        $manager->addMassTokens(15, allowLeechDisabled: false);
        foreach ($this->userList as $user) {
            $user->flush();
        }
        $this->assertEquals(10, $this->userList[0]->tokenCount(), 'uman-masstoken-add-more-0');
        $this->assertEquals(25, $this->userList[1]->tokenCount(), 'uman-masstoken-add-more-1');
        $this->assertEquals(25, $this->userList[2]->tokenCount(), 'uman-masstoken-add-more-2');

        $this->userList[1]->toggleAttr('no-fl-gifts', true);
        $manager->addMassTokens(12, allowLeechDisabled: false);
        foreach ($this->userList as $user) {
            $user->flush();
        }
        $this->assertEquals(10, $this->userList[0]->tokenCount(), 'uman-masstoken-add-fl-0');
        $this->assertEquals(25, $this->userList[1]->tokenCount(), 'uman-masstoken-add-fl-1');
        $this->assertEquals(37, $this->userList[2]->tokenCount(), 'uman-masstoken-add-fl-2');

        $manager->addMassTokens(11, allowLeechDisabled: true);
        foreach ($this->userList as $user) {
            $user->flush();
        }
        $this->assertEquals(21, $this->userList[0]->tokenCount(), 'uman-masstoken-add-force-0');
        $this->assertEquals(25, $this->userList[1]->tokenCount(), 'uman-masstoken-add-force-1');
        $this->assertEquals(48, $this->userList[2]->tokenCount(), 'uman-masstoken-add-force-2');

        $this->assertEquals(
            1,
            $manager->disableUserList(
                [$this->userList[2]->id],
                UserAuditEvent::activity,
                'phpunit fltoken',
                \Gazelle\Manager\User::DISABLE_MANUAL,
            ),
            'uman-masstoken-disable'
        );
        $manager->clearMassTokens(18, allowLeechDisabled: false, excludeDisabled: true);
        foreach ($this->userList as $user) {
            $user->flush();
        }
        $this->assertEquals(21, $this->userList[0]->tokenCount(), 'uman-masstoken-clear-0');
        $this->assertEquals(18, $this->userList[1]->tokenCount(), 'uman-masstoken-clear-1');
        $this->assertEquals(48, $this->userList[2]->tokenCount(), 'uman-masstoken-clear-2');

        $manager->clearMassTokens(17, allowLeechDisabled: true, excludeDisabled: false);
        foreach ($this->userList as $user) {
            $user->flush();
        }
        $this->assertEquals(17, $this->userList[0]->tokenCount(), 'uman-masstoken-clear2-0');
        $this->assertEquals(17, $this->userList[1]->tokenCount(), 'uman-masstoken-clear2-1');
        $this->assertEquals(48, $this->userList[2]->tokenCount(), 'uman-masstoken-clear2-2');

        $manager->clearMassTokens(16, allowLeechDisabled: false, excludeDisabled: false);
        foreach ($this->userList as $user) {
            $user->flush();
        }
        $this->assertEquals(17, $this->userList[0]->tokenCount(), 'uman-masstoken-clear3-0');
        $this->assertEquals(16, $this->userList[1]->tokenCount(), 'uman-masstoken-clear3-1');
        $this->assertEquals(48, $this->userList[2]->tokenCount(), 'uman-masstoken-clear3-2');

        $manager->clearMassTokens(15, allowLeechDisabled: true, excludeDisabled: true);
        foreach ($this->userList as $user) {
            $user->flush();
        }
        $this->assertEquals(15, $this->userList[0]->tokenCount(), 'uman-masstoken-clear4-0');
        $this->assertEquals(15, $this->userList[1]->tokenCount(), 'uman-masstoken-clear4-1');
        $this->assertEquals(15, $this->userList[2]->tokenCount(), 'uman-masstoken-clear4-2');
    }

    public function testUserRatioWatch(): void {
        $manager = new Manager\User();
        $this->userList[] = Helper::makeUser('um4.' . randomString(10), 'userman', enable: true, clearInbox: true);
        $user = $this->userList[0];
        $this->assertFalse($user->onRatioWatch(), 'utest-personal-on-ratio-watch');
        $this->assertEquals(0.0, $user->requiredRatio(), 'utest-required-ratio');
        $this->assertEquals(0, $user->downloadedOnRatioWatch(), 'utest-download-ratio-watch');
        $idList = array_map(fn ($u) => $u->id, $this->userList);

        // put users onto ratio watch
        $GiB50 = 50 * 1_105_507_304;
        $db = DB::DB();
        $db->prepared_query("
            UPDATE users_leech_stats SET
                Downloaded = ?
            WHERE UserID IN (" . placeholders($idList) . ")
            ", $GiB50, ...$idList
        );
        $db->prepared_query("
            INSERT INTO users_torrent_history
                   (UserID, NumTorrents, Date, Time)
            VALUES " . placeholders($idList, '(?, 1, unix_timestamp(now()), 259200)') . "
            ", ...$idList
        );
        $result = $manager->updateRatioRequirements();
        $this->assertCount(29, $result, 'uman-ratiowatch-update');
        $this->assertGreaterThanOrEqual(
            count($idList),
            $result['07-weight'][0],
            'uman-ratiowatch-set-action',
        );
        $this->assertEquals(
            $idList,
            $manager->ratioWatchSetList(),
            'uman-ratiowatch-set-list',
        );
        $this->assertEquals(
            count($idList),
            $manager->ratioWatchSet(),
            'uman-ratiowatch-set-action',
        );
        foreach ($this->userList as $user) {
            $user->flush();
        }

        $receiver = $this->userList[0]->inbox();
        $pmMan    = new Manager\PM($receiver->user());
        $list     = $receiver->messageList($pmMan, 2, 0);
        $this->assertEquals(1, $receiver->messageTotal(), 'uman-ratiowatch-pm-count');
        $this->assertEquals(
            'You have been put on Ratio Watch',
            $list[0]->subject(),
            'uman-ratiowatch-pm-subject',
        );

        // nuke the recent pms
        foreach ($this->userList as $user) {
            $user->setField('Enabled', '1')->modify();
            $pmMan = new Manager\PM($user);
            foreach ($user->inbox()->messageList($pmMan, 1, 0) as $pm) {
                $pm->remove();
            }
        }

        // user[0] doubles down and is blocked
        $db->prepared_query("
            UPDATE users_info ui
            INNER JOIN users_leech_stats uls USING (UserID)
            SET
                ui.RatioWatchDownload = ?,
                uls.Downloaded = uls.Downloaded + ?
            WHERE ui.UserID = ?
            ", $GiB50, $GiB50, $this->userList[0]->id
        );
        $this->userList[0]->flush();
        $this->assertEquals([$this->userList[0]->id], $manager->ratioWatchBlockList(), 'uman-ratiowatch-block-list');
        $this->assertEquals(1, $manager->ratioWatchBlock(), 'uman-ratiowatch-do-block');
        $this->userList[0]->flush();

        $receiver = $this->userList[0]->inbox();
        $pmMan    = new Manager\PM($receiver->user());
        $this->assertEquals(1, $receiver->messageTotal(), 'uman-ratiowatch-pm-block-count');
        $list = $receiver->messageList($pmMan, 2, 0);
        $this->assertEquals('Your download privileges have been removed', $list[0]->subject(), 'uman-ratiowatch-pm-block-subject');
        $this->assertFalse($this->userList[0]->canLeech(), 'uman-user0-no-canleech');

        // user[1] makes good and is cleared
        $db->prepared_query("
            UPDATE users_leech_stats SET
                Uploaded = Uploaded + ?
            WHERE UserID = ?
            ", $GiB50, $this->userList[1]->id
        );

        // ratio watch ends
        $db->prepared_query("
            UPDATE users_info SET
                RatioWatchEnds = now()
            WHERE UserID IN (" . placeholders($idList) . ")
            ", ...$idList
        );
        foreach ($this->userList as $user) {
            $user->flush();
        }

        // user[1] is cleared
        $this->assertEquals([$this->userList[1]->id], $manager->ratioWatchClearList(), 'uman-ratiowatch-clear-list');
        $this->assertEquals(1, $manager->ratioWatchClear(), 'uman-ratiowatch-do-clear');
        $this->assertEquals(0, $manager->ratioWatchClear(), 'uman-ratiowatch-reprocess-clear');
        $this->userList[1]->flush();

        $receiver = $this->userList[1]->inbox();
        $pmMan    = new Manager\PM($receiver->user());
        $this->assertEquals(1, $receiver->messageTotal(), 'uman-ratiowatch-pm-clear-count');
        $list = $receiver->messageList($pmMan, 2, 0);
        $this->assertEquals('You have been taken off Ratio Watch', $list[0]->subject(), 'uman-ratiowatch-pm-clear-subject');
        $this->assertTrue($this->userList[1]->canLeech(), 'uman-user1-canleech');

        // user[3] is cleared
        $this->assertEquals(
            1,
            $this->userList[3]->history()->resetRatioWatch(),
            'uman-ratiowatch-reset'
        );

        // user[2] did nothing, loses download privileges
        $this->assertEquals([$this->userList[2]->id], $manager->ratioWatchEngageList(), 'uman-ratiowatch-engage-list');
        $this->assertEquals(1, $manager->ratioWatchEngage(), 'uman-ratiowatch-do-engage');
        $this->assertEquals(0, $manager->ratioWatchEngage(), 'uman-ratiowatch-reprocess-engage');
        $this->userList[2]->flush();

        $receiver = $this->userList[2]->inbox();
        $pmMan    = new Manager\PM($receiver->user());
        $this->assertEquals(1, $receiver->messageTotal(), 'uman-ratiowatch-pm-engage-count');
        $list = $receiver->messageList($pmMan, 2, 0);
        $this->assertEquals(
            'Your downloading privileges have been suspended',
            $list[0]->subject(),
            'uman-ratiowatch-pm-engage-subject'
        );
        $this->assertFalse($this->userList[2]->canLeech(), 'uman-user2-no-canleech');
    }

    public function testSendCustomPM(): void {
        $manager = new Manager\User();
        $this->assertEquals(
            2,
            $manager->sendCustomPM(
                $this->userList[0],
                'phpunit sendCustomPMTest',
                'phpunit sendCustomPMTest %USERNAME% message',
                [$this->userList[1]->id, $this->userList[2]->id],
            ),
            'uman-send-custom-pm'
        );

        $receiver = $this->userList[2]->inbox();
        $pmMan    = new Manager\PM($receiver->user());
        $this->assertEquals(1, $receiver->messageTotal(), 'uman-custom-pm-count');
        $list = $receiver->messageList($pmMan, 2, 0);
        $this->assertEquals(
            'phpunit sendCustomPMTest',
            $list[0]->subject(),
            'uman-custom-pm-subject'
        );
        $postlist = $list[0]->postlist(10, 0);
        $postId = $postlist[0]['id'];
        $pm = $pmMan->findByPostId($postId);
        $this->assertInstanceOf(PM::class, $pm, 'uman-found-pm');
        $this->assertStringContainsString(
            $this->userList[2]->username(),
            (string)$pm->postBody($postId),
            'uman-custom-pm-body'
        );
    }

    public function testUserclassFlush(): void {
        $manager = new Manager\User();
        $administratorId = (int)current(array_filter(
            $manager->classList(),
            fn ($class) => $class['Name'] == 'Administrator'
        ))['ID'];
        $alphaTeamId = (int)current(
            array_filter($manager->classList(),
            fn ($class) => $class['Name'] == 'Alpha Team'
        ))['ID'];

        $this->userList[0]->addClasses([$administratorId]);
        $this->userList[1]->addClasses([$alphaTeamId]);
        $this->assertEquals(1, $manager->flushUserclass($administratorId), 'uman-flush-userclass-administrator');
        $this->assertEquals(1, $manager->flushUserclass($alphaTeamId), 'uman-flush-userclass-alphateam');

        $classLevelList = $manager->classLevelList();
        $this->assertCount(27, $classLevelList, 'uman-classlevel-total');
        $this->assertEqualsCanonicalizing(
            ['ID', 'Name', 'Level', 'Secondary', 'badge'],
            array_keys($classLevelList[100]),
            'uman-classlevel-total',
        );
    }

    public function testUserflow(): void {
        global $Cache;
        $Cache->delete_value(Manager\User::USERFLOW_KEY);
        $manager  = new Manager\User();
        $userflow = $manager->userflow();
        $this->assertGreaterThanOrEqual(0, count($userflow), 'uman-userflow-is-array');
        $recent = end($userflow);
        $this->assertGreaterThanOrEqual(0, count($recent), 'uman-userflow-recent-is-array');
        $this->assertEquals(['Week', 'created', 'disabled'], array_keys($recent), 'userman-recent-keys');
        $this->assertGreaterThan(
            0,
            $manager->userflowTotal(),
            'uman-userflow-total'
        );
        $details = $manager->userflowDetails(1, 0);
        $this->assertCount(1, $details, 'uman-userflow-details-count');
        $this->assertEqualsCanonicalizing(
            ["date", "month", "created", "manual", "ratio", "inactivity"],
            array_keys($details[0]),
            'uman-userflow-details-keys'
        );
    }

    public function testUserStaffList(): void {
        $manager = new Manager\User();
        $this->userList[1]->privilege()->addSecondaryClass(FLS_TEAM);
        $this->userList[2]->setField('PermissionID', SYSOP)->modify();
        global $Cache;
        $Cache->delete_multi(['idfls', 'staff_class']);

        $this->assertContains(
            $this->userList[1]->id,
            array_map(fn ($u) => $u->id, $manager->flsList()),
            'uman-fls-list'
        );
        $this->assertContains(
            $this->userList[2]->id,
            array_map(fn ($u) => $u->id, $manager->staffList()),
            'uman-staff-list'
        );
        $this->assertEqualsCanonicalizing(
            ['Forum Moderator', 'Moderator', 'Administrator', 'Sysop'],
            array_column($manager->staffClassList(), 'Name'),
            'uman-staff-class-list',
        );
    }
}
