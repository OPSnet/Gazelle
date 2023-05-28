<?php

use \PHPUnit\Framework\TestCase;

require_once(__DIR__ . '/../../lib/bootstrap.php');
require_once(__DIR__ . '/../helper.php');

/**
 * This test can be a little flakey locally if it fails halfway through. To fix up:
 * DELETE ul FROM users_levels ul LEFT JOIN users_info ui USING (userid) WHERE ui.userid IS NULL; DELETE FROM permissions WHERE level = 666;
 */

class PrivilegeTest extends TestCase {
    protected array $userList;

    public function setUp(): void {
        $this->userList = [
            'admin' => Helper::makeUser('priv1.' . randomString(6), 'request'),
            'user'  => Helper::makeUser('priv2.' . randomString(6), 'request'),
        ];
        $this->userList['admin']->setUpdate('PermissionID', SYSOP)->modify();
    }

    public function tearDown(): void {
        foreach ($this->userList as $user) {
            $user->remove();
        }
    }

    public function testPrivilegeCreate(): void {
        $privilegeList = Gazelle\Manager\Privilege::privilegeList();
        $this->assertCount(125, $privilegeList, 'privilege-total');

        $manager = new Gazelle\Manager\Privilege;
        $this->assertNull($manager->findByLevel(666), 'privilege-find-none'); // if this fails check the `permissions` table

        // create a privilege
        $badge     = 'X' . strtoupper(randomString(2));
        $name      = 'phpunit-priv-' . randomString(4);
        $nameList  = array_keys($privilegeList);
        $custom    = array_splice($nameList, 0, 5);
        $privilege = $manager->create(
            badge:        $badge,
            name:         $name,
            values:       $custom,
            level:        666,
            secondary:    1,
            forums:       '',
            staffGroupId: null,
            displayStaff: false
        );
        $this->assertEquals(
            $privilege->id(),
            $manager->findByLevel(666)->id(), /** @phpstan-ignore-line */
            'privilege-find-by-level'
        );
        $this->assertEquals($badge, $privilege->badge(), 'privilege-badge');
        $this->assertEquals(666, $privilege->level(), 'privilege-level');
        $this->assertCount(0, $privilege->permittedForums(), 'privilege-permitted-forums');
        $this->assertFalse($privilege->displayStaff(), 'privilege-display-staff');
        $this->assertTrue($privilege->isSecondary(), 'privilege-is-secondary');

        // assign privilege to user
        $this->assertEquals(0, $privilege->userTotal(), 'privilege-has-no-users-yet');
        $this->assertEquals(0, $this->userList['user']->secondaryClassesList()[$privilege->name()]['isSet'], 'privilege-user-no-secondary-yet');
        $this->assertEquals(1, $this->userList['user']->addClasses([$privilege->id()]), 'privilege-add-secondary');
        $this->assertEquals(1, $this->userList['user']->secondaryClassesList()[$privilege->name()]['isSet'], 'privilege-user-no-secondary-yet');
        $this->assertEquals(1, $privilege->flush()->userTotal(), 'privilege-has-one-user');

        // TODO: User\Privilege should take care of adding and removing secondary classes
        $userPriv = new Gazelle\User\Privilege($this->userList['user']);
        $this->assertEquals(666, $userPriv->maxSecondaryLevel(), 'privilege-user-max-level');
        $this->assertEquals([$privilege->id() => $name], $userPriv->secondaryClassList(), 'privilege-user-list');
        $this->assertEquals([$badge => $name], $userPriv->badgeList(), 'privilege-user-badge');

        // revoke privilege
        $this->assertEquals(1, $this->userList['user']->removeClasses([$privilege->id()]), 'privilege-remove-secondary');
        $this->assertEquals(0, $this->userList['user']->secondaryClassesList()[$privilege->name()]['isSet'], 'privilege-user-no-more-secondary');
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

    /**
     * @dataProvider privilegeProvider
     */
    public function testPrivilegeFls(int $privilegeId, string $method, string $label): void {
        $user = $this->userList['user'];
        $this->assertFalse($user->$method(), "privilege-user-not-$label");
        $this->assertEquals(1, $user->addClasses([$privilegeId]), "privilege-add-$label");
        $this->assertTrue($user->$method(), "privilege-user-now-$label");
        $this->assertEquals(1, $user->removeClasses([$privilegeId]), "privilege-remove-$label");
    }
}
