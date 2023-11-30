<?php

use PHPUnit\Framework\TestCase;

require_once(__DIR__ . '/../../../lib/bootstrap.php');
require_once(__DIR__ . '/../../helper.php');

class UserManagerTest extends TestCase {
    protected array $userList;

    public function setUp(): void {
        $this->userList = [
            Helper::makeUser('um1.' . randomString(10), 'userman', enable: true, clearInbox: true),
            Helper::makeUser('um2.' . randomString(10), 'userman', enable: true, clearInbox: true),
            Helper::makeUser('um3.' . randomString(10), 'userman', enable: true, clearInbox: true),
        ];
    }

    public function tearDown(): void {
        foreach ($this->userList as $user) {
            $user->remove();
        }
    }

    public function testCycleAuthKeys(): void {
        $userMan = new Gazelle\Manager\User;
        $this->assertEquals(
            (int)\Gazelle\DB::DB()->scalar("
                SELECT count(*) FROM users_main
            "),
            $userMan->cycleAuthKeys(),
            'uman-auth-keys'
        );
    }

    public function testDisableUserList(): void {
        $userMan = new Gazelle\Manager\User;
        $idList  = array_map(fn($u) => $u->id(), $this->userList);

        $this->assertEquals(3, $userMan->disableUserList(new \Gazelle\Tracker, $idList, 'phpunit mass disable', 3), 'uman-mass-disable');
        $this->userList[2]->flush();
        $this->assertTrue($this->userList[2]->isDisabled(), 'uman-disabled-user2');
        $this->assertFalse($this->userList[2]->isEnabled(), 'uman-enabled-user2');
        $this->assertFalse($this->userList[2]->isUnconfirmed(), 'uman-unconfirmed-user2');
    }

    public function testModifyUserAttr(): void {
        $userMan = new Gazelle\Manager\User;
        $idList  = array_map(fn($u) => $u->id(), $this->userList);
        $this->assertFalse($this->userList[0]->hasAttr('hide-tags'), 'uman-attr-no-attr');

        $this->assertEquals(3, $userMan->modifyAttr($idList, 'hide-tags', true), 'uman-attr-modify');
        $this->assertTrue($this->userList[0]->flush()->hasAttr('hide-tags'), 'uman-attr-has-attr');
    }

    public function testModifyUserMassToken(): void {
        $userMan = new Gazelle\Manager\User;
        $idList  = array_map(fn($u) => $u->id(), $this->userList);
        $this->assertEquals(0, $this->userList[0]->tokenCount(), 'uman-masstoken-initial');

        $userMan->addMassTokens(10, allowLeechDisabled: false);
        foreach ($this->userList as $user) {
            $user->flush();
        }
        $this->assertEquals(10, $this->userList[0]->tokenCount(), 'uman-masstoken-add-all-0');
        $this->assertEquals(10, $this->userList[1]->tokenCount(), 'uman-masstoken-add-all-1');
        $this->assertEquals(10, $this->userList[2]->tokenCount(), 'uman-masstoken-add-all-2');

        $this->userList[0]->setField('can_leech', 0)->modify();
        $userMan->addMassTokens(15, allowLeechDisabled: false);
        foreach ($this->userList as $user) {
            $user->flush();
        }
        $this->assertEquals(10, $this->userList[0]->tokenCount(), 'uman-masstoken-add-more-0');
        $this->assertEquals(25, $this->userList[1]->tokenCount(), 'uman-masstoken-add-more-1');
        $this->assertEquals(25, $this->userList[2]->tokenCount(), 'uman-masstoken-add-more-2');

        $this->userList[1]->toggleAttr('no-fl-gifts', true);
        $userMan->addMassTokens(12, allowLeechDisabled: false);
        foreach ($this->userList as $user) {
            $user->flush();
        }
        $this->assertEquals(10, $this->userList[0]->tokenCount(), 'uman-masstoken-add-fl-0');
        $this->assertEquals(25, $this->userList[1]->tokenCount(), 'uman-masstoken-add-fl-1');
        $this->assertEquals(37, $this->userList[2]->tokenCount(), 'uman-masstoken-add-fl-2');

        $userMan->addMassTokens(11, allowLeechDisabled: true);
        foreach ($this->userList as $user) {
            $user->flush();
        }
        $this->assertEquals(21, $this->userList[0]->tokenCount(), 'uman-masstoken-add-force-0');
        $this->assertEquals(25, $this->userList[1]->tokenCount(), 'uman-masstoken-add-force-1');
        $this->assertEquals(48, $this->userList[2]->tokenCount(), 'uman-masstoken-add-force-2');

        $this->assertEquals(
            1,
            $userMan->disableUserList(new \Gazelle\Tracker, [$this->userList[2]->id()], 'phpunit fltoken', \Gazelle\Manager\User::DISABLE_MANUAL),
            'uman-masstoken-disable'
        );
        $userMan->clearMassTokens(18, allowLeechDisabled: false, excludeDisabled: true);
        foreach ($this->userList as $user) {
            $user->flush();
        }
        $this->assertEquals(21, $this->userList[0]->tokenCount(), 'uman-masstoken-clear-0');
        $this->assertEquals(18, $this->userList[1]->tokenCount(), 'uman-masstoken-clear-1');
        $this->assertEquals(48, $this->userList[2]->tokenCount(), 'uman-masstoken-clear-2');

        $userMan->clearMassTokens(17, allowLeechDisabled: true, excludeDisabled: false);
        foreach ($this->userList as $user) {
            $user->flush();
        }
        $this->assertEquals(17, $this->userList[0]->tokenCount(), 'uman-masstoken-clear2-0');
        $this->assertEquals(17, $this->userList[1]->tokenCount(), 'uman-masstoken-clear2-1');
        $this->assertEquals(48, $this->userList[2]->tokenCount(), 'uman-masstoken-clear2-2');

        $userMan->clearMassTokens(16, allowLeechDisabled: false, excludeDisabled: false);
        foreach ($this->userList as $user) {
            $user->flush();
        }
        $this->assertEquals(17, $this->userList[0]->tokenCount(), 'uman-masstoken-clear3-0');
        $this->assertEquals(16, $this->userList[1]->tokenCount(), 'uman-masstoken-clear3-1');
        $this->assertEquals(48, $this->userList[2]->tokenCount(), 'uman-masstoken-clear3-2');

        $userMan->clearMassTokens(15, allowLeechDisabled: true, excludeDisabled: true);
        foreach ($this->userList as $user) {
            $user->flush();
        }
        $this->assertEquals(15, $this->userList[0]->tokenCount(), 'uman-masstoken-clear4-0');
        $this->assertEquals(15, $this->userList[1]->tokenCount(), 'uman-masstoken-clear4-1');
        $this->assertEquals(15, $this->userList[2]->tokenCount(), 'uman-masstoken-clear4-2');
    }

    public function testUserRatioWatch(): void {
        $db      = \Gazelle\DB::DB();
        $tracker = new \Gazelle\Tracker;
        $userMan = new \Gazelle\Manager\User;
        $idList  = array_map(fn($u) => $u->id(), $this->userList);

        // put users onto ratio watch
        $GiB50 = 50 * 1_105_507_304;
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
        $this->assertEquals(3, $userMan->updateRatioRequirements(), 'uman-ratiowatch-update');
        $this->assertEquals($idList, $userMan->ratioWatchSetList(), 'uman-ratiowatch-set-list');
        $this->assertEquals(3, $userMan->ratioWatchSet(), 'uman-ratiowatch-set-action');
        foreach ($this->userList as $user) {
            $user->flush();
        }

        $receiver = $this->userList[0]->inbox();
        $pmMan    = new \Gazelle\Manager\PM($receiver->user());
        $this->assertEquals(1, $receiver->messageTotal(), 'uman-ratiowatch-pm-count');
        $list = $receiver->messageList($pmMan, 2, 0);
        $this->assertEquals('You have been put on Ratio Watch', $list[0]->subject(), 'uman-ratiowatch-pm-subject');

        // nuke the recent pms
        foreach ($this->userList as $user) {
            $user->setField('Enabled', '1')->modify();
            $pmMan = new Gazelle\Manager\PM($user);
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
            ", $GiB50, $GiB50, $this->userList[0]->id()
        );
        $this->userList[0]->flush();
        $this->assertEquals([$this->userList[0]->id()], $userMan->ratioWatchBlockList(), 'uman-ratiowatch-block-list');
        $this->assertEquals(1, $userMan->ratioWatchBlock($tracker), 'uman-ratiowatch-do-block');
        $this->userList[0]->flush();

        $receiver = $this->userList[0]->inbox();
        $pmMan    = new \Gazelle\Manager\PM($receiver->user());
        $this->assertEquals(1, $receiver->messageTotal(), 'uman-ratiowatch-pm-block-count');
        $list = $receiver->messageList($pmMan, 2, 0);
        $this->assertEquals('Your download privileges have been removed', $list[0]->subject(), 'uman-ratiowatch-pm-block-subject');
        $this->assertFalse($this->userList[0]->canLeech(), 'uman-user0-no-canleech');

        // user[1] makes good and is cleared
        $db->prepared_query("
            UPDATE users_leech_stats SET
                Uploaded = Uploaded + ?
            WHERE UserID = ?
            ", $GiB50, $this->userList[1]->id()
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
        $this->assertEquals([$this->userList[1]->id()], $userMan->ratioWatchClearList(), 'uman-ratiowatch-clear-list');
        $this->assertEquals(1, $userMan->ratioWatchClear($tracker), 'uman-ratiowatch-do-clear');
        $this->assertEquals(0, $userMan->ratioWatchClear($tracker), 'uman-ratiowatch-reprocess-clear');
        $this->userList[1]->flush();

        $receiver = $this->userList[1]->inbox();
        $pmMan    = new \Gazelle\Manager\PM($receiver->user());
        $this->assertEquals(1, $receiver->messageTotal(), 'uman-ratiowatch-pm-clear-count');
        $list = $receiver->messageList($pmMan, 2, 0);
        $this->assertEquals('You have been taken off Ratio Watch', $list[0]->subject(), 'uman-ratiowatch-pm-clear-subject');
        $this->assertTrue($this->userList[1]->canLeech(), 'uman-user1-canleech');

        // user[2] did nothing, loses download privileges
        $this->assertEquals([$this->userList[2]->id()], $userMan->ratioWatchEngageList(), 'uman-ratiowatch-engage-list');
        $this->assertEquals(1, $userMan->ratioWatchEngage($tracker), 'uman-ratiowatch-do-engage');
        $this->assertEquals(0, $userMan->ratioWatchEngage($tracker), 'uman-ratiowatch-reprocess-engage');
        $this->userList[2]->flush();

        $receiver = $this->userList[2]->inbox();
        $pmMan    = new \Gazelle\Manager\PM($receiver->user());
        $this->assertEquals(1, $receiver->messageTotal(), 'uman-ratiowatch-pm-engage-count');
        $list = $receiver->messageList($pmMan, 2, 0);
        $this->assertEquals('Your downloading privileges have been suspended', $list[0]->subject(), 'uman-ratiowatch-pm-engage-subject');
        $this->assertFalse($this->userList[2]->canLeech(), 'uman-user2-no-canleech');
    }

    public function testSendCustomPM(): void {
        $userMan = new \Gazelle\Manager\User;
        $this->assertEquals(
            2,
            $userMan->sendCustomPM(
                $this->userList[0],
                'phpunit sendCustomPMTest',
                'phpunit sendCustomPMTest %USERNAME% message',
                [$this->userList[1]->id(), $this->userList[2]->id()],
            ),
            'uman-send-custom-pm'
        );

        $receiver = $this->userList[2]->inbox();
        $pmMan    = new \Gazelle\Manager\PM($receiver->user());
        $this->assertEquals(1, $receiver->messageTotal(), 'uman-custom-pm-count');
        $list = $receiver->messageList($pmMan, 2, 0);
        $this->assertEquals('phpunit sendCustomPMTest', $list[0]->subject(), 'uman-custom-pm-subject');
        $postlist = $list[0]->postlist(10, 0);
        $postId = $postlist[0]['id'];
        $pm = $pmMan->findByPostId($postId);
        $this->assertStringContainsString($this->userList[2]->username(), $pm->postBody($postId), 'uman-custom-pm-body');
    }

    public function testUserclassFlush(): void {
        $userMan = new \Gazelle\Manager\User;
        $administratorId = (int)current(array_filter($userMan->classList(), fn($class) => $class['Name'] == 'Administrator'))['ID'];
        $alphaTeamId = (int)current(array_filter($userMan->classList(), fn($class) => $class['Name'] == 'Alpha Team'))['ID'];

        $this->userList[0]->addClasses([$administratorId]);
        $this->userList[1]->addClasses([$alphaTeamId]);
        $this->assertEquals(1, $userMan->flushUserclass($administratorId), 'uman-flush-userclass-administrator');
        $this->assertEquals(1, $userMan->flushUserclass($alphaTeamId), 'uman-flush-userclass-alphateam');
    }

    public function testUserflow(): void {
        $userflow = (new \Gazelle\Manager\User)->userflow();
        $this->assertIsArray($userflow, 'uman-userflow-is-array');
        $recent = end($userflow);
        $this->assertIsArray($recent, 'uman-userflow-recent-is-array');
        $this->assertEquals(['Week', 'created', 'disabled'], array_keys($recent), 'userman-recent-keys');
    }
}
