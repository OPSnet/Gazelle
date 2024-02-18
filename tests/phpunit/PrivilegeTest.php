<?php

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

require_once(__DIR__ . '/../../lib/bootstrap.php');
require_once(__DIR__ . '/../helper.php');

define('FAKE_LEVEL', 666);

/**
 * This test can be a little flakey locally if it fails halfway through. To fix up:
 * DELETE ul FROM users_levels ul LEFT JOIN users_info ui USING (userid) WHERE ui.userid IS NULL; DELETE FROM permissions WHERE level = ?; -- FAKE_LEVEL
 */

class PrivilegeTest extends TestCase {
    protected array $userList;

    public function setUp(): void {
        $this->userList = [
            'admin' => Helper::makeUser('priv1.' . randomString(6), 'request'),
            'user'  => Helper::makeUser('priv2.' . randomString(6), 'request'),
        ];
        $this->userList['admin']->setField('PermissionID', SYSOP)->modify();
    }

    public function tearDown(): void {
        foreach ($this->userList as $user) {
            $user->remove();
        }
    }

    public function testPrivilegeCreate(): void {
        $privilegeList = Gazelle\Manager\Privilege::privilegeList();
        $this->assertCount(127, $privilegeList, 'privilege-total');

        $manager = new Gazelle\Manager\Privilege();
        $this->assertNull($manager->findByLevel(FAKE_LEVEL), 'privilege-find-none'); // if this fails, check the `permissions` table

        // create a privilege
        $badge     = 'X' . strtoupper(randomString(2));
        $name      = 'phpunit-priv-' . randomString(4);
        $nameList  = array_keys($privilegeList);
        $custom    = array_splice($nameList, 0, 5);
        $privilege = $manager->create(
            badge:        $badge,
            name:         $name,
            values:       $custom,
            level:        FAKE_LEVEL,
            secondary:    1,
            forums:       '',
            staffGroupId: null,
            displayStaff: false
        );
        $find = $manager->findByLevel(FAKE_LEVEL);
        $this->assertInstanceOf(\Gazelle\Privilege::class, $find, 'privilege-find-by-level');
        $this->assertEquals($privilege->id(), $find->id(), 'privilege-found-self');
        $this->assertEquals($badge, $privilege->badge(), 'privilege-badge');
        $this->assertEquals(FAKE_LEVEL, $privilege->level(), 'privilege-level');
        $this->assertCount(0, $privilege->permittedForums(), 'privilege-permitted-forums');
        $this->assertFalse($privilege->displayStaff(), 'privilege-display-staff');
        $this->assertTrue($privilege->isSecondary(), 'privilege-is-secondary');
        $this->assertEquals($privilege->id(), $manager->findById($privilege->id())->id(), 'privilege-find-by-id');
        $this->assertNull($manager->findById(1 + $privilege->id()), 'privilege-find-null');

        // assign privilege to user
        $this->assertEquals(0, $privilege->userTotal(), 'privilege-has-no-users-yet');
        $this->assertEquals(0, $this->userList['user']->privilege()->secondaryClassesList()[$privilege->name()]['isSet'], 'privilege-user-no-secondary-yet');
        $this->assertEquals(1, $this->userList['user']->addClasses([$privilege->id()]), 'privilege-add-secondary');
        $this->assertEquals(1, $this->userList['user']->privilege()->secondaryClassesList()[$privilege->name()]['isSet'], 'privilege-user-no-secondary-yet');
        $this->assertEquals(1, $privilege->flush()->userTotal(), 'privilege-has-one-user');

        // TODO: User\Privilege should take care of adding and removing secondary classes
        $userPriv = $this->userList['user']->privilege();
        $this->assertEquals(FAKE_LEVEL, $userPriv->maxSecondaryLevel(), 'privilege-user-max-level');
        $this->assertEquals([$privilege->id() => $name], $userPriv->secondaryClassList(), 'privilege-user-list');
        $this->assertEquals([$badge => $name], $userPriv->badgeList(), 'privilege-user-badge');

        // revoke privilege
        $this->assertEquals(1, $this->userList['user']->removeClasses([$privilege->id()]), 'privilege-remove-secondary');
        $this->assertEquals(0, $this->userList['user']->privilege()->secondaryClassesList()[$privilege->name()]['isSet'], 'privilege-user-no-more-secondary');
        $this->assertEquals(0, $privilege->flush()->userTotal(), 'privilege-has-no-users');

        $this->assertEquals(1, $privilege->remove(), 'privilege-remove');
    }

    public static function privilegeProvider(): array {
        return [
            // privId     method           label
            [FLS_TEAM,    'isFls',         'fls'],
            [INTERVIEWER, 'isInterviewer', 'interviewer'],
            [RECRUITER,   'isRecruiter',   'recruiter'],
        ];
    }

    #[DataProvider('privilegeProvider')]
    public function testPrivilegeSecondary(int $privilegeId, string $method, string $label): void {
        $user = $this->userList['user'];
        $privilege = new Gazelle\Privilege($privilegeId);
        $this->assertFalse($user->$method(), "privilege-user-not-$label");
        $this->assertEquals(1, $user->addClasses([$privilegeId]), "privilege-add-$label");
        $this->assertTrue($user->$method(), "privilege-user-now-$label");
        // TODO: the method name and parameter could be improved
        $this->assertTrue($user->privilege()->hasSecondaryClass($privilege->name()), "privilege-has-secondary-$label");
        $this->assertEquals(1, $user->removeClasses([$privilegeId]), "privilege-remove-$label");
        $this->assertFalse($user->$method(), "privilege-user-no-longer-$label");
        $this->assertFalse($user->privilege()->hasSecondaryClass($privilege->name()), "privilege-no-longerhas-secondary-$label");
    }

    public function testPrivilegeBadge(): void {
        $manager = new Gazelle\Manager\Privilege();
        $flsList = array_values(array_filter($manager->usageList(), fn($p) => $p['id'] == FLS_TEAM));
        $total = $flsList[0]['total'];

        $user = $this->userList['user'];
        $this->assertEquals(3, $user->addClasses([FLS_TEAM, INTERVIEWER, RECRUITER]), "privilege-add-multi-secondary");
        $this->assertEquals(0, $user->addClasses([FLS_TEAM, INTERVIEWER, RECRUITER]), "privilege-add-multi-no-op");
        $userPriv = $user->privilege();
        $this->assertEquals(
            [
                'FLS' => 'First Line Support',
                'IN'  => 'Interviewer',
            ],
            $userPriv->badgeList(),
            'privilege-badge-list',
        );
        $this->assertGreaterThan(0, count($userPriv->secondaryPrivilegeList()), 'privilege-secondary-list');
        $flsList = array_values(array_filter($manager->usageList(), fn($p) => $p['id'] == FLS_TEAM));
        $this->assertEquals($total + 1, $flsList[0]['total'], 'privilege-one-new-fls');
    }
}
