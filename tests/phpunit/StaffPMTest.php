<?php

use PHPUnit\Framework\TestCase;

require_once(__DIR__ . '/../../lib/bootstrap.php');
require_once(__DIR__ . '/../helper.php');

class StaffPMTest extends TestCase {
    protected \Gazelle\Manager\StaffPM $spMan;

    protected \Gazelle\User $fls;
    protected \Gazelle\User $mod;
    protected \Gazelle\User $sysop;
    protected \Gazelle\User $user;

    public function setUp(): void {
        $this->spMan = new \Gazelle\Manager\StaffPM;
        $this->fls   = Helper::makeUser('spm_fls_' . randomString(10), 'staffpm');
        $this->mod   = Helper::makeUser('spm_mod_' . randomString(10), 'staffpm');
        $this->sysop = Helper::makeUser('spm_sysop_' . randomString(10), 'staffpm');
        $this->user  = Helper::makeUser('spm_user_' . randomString(10), 'staffpm');

        $this->fls->addClasses([FLS_TEAM]);
        $this->mod->setField('PermissionID', MOD)->modify();
        $this->sysop->setField('PermissionID', SYSOP)->modify();
    }

    public function tearDown(): void {
        $this->fls->remove();
        $this->mod->remove();
        $this->sysop->remove();
        $this->user->remove();
    }

    public function testCreate(): void {
        $spm = $this->spMan->create($this->user, 0, 'for FLS', 'message handled by FLS');
        $this->assertNotNull($spm, 'spm-fls-create');
        $this->assertEquals(0, $spm->assignedUserId(), 'spm-fls-no-assignedUser');
        $this->assertEquals(0, $spm->classLevel(), 'spm-fls-classlevel-0');
        $this->assertEquals('for FLS', $spm->subject(), 'spm-fls-subject');
        $this->assertEquals('Level 0', $spm->userclassName(), 'spm-fls-userclass');
        $this->assertEquals($this->user->id(), $spm->userId(), 'spm-fls-author-id');
        $this->assertTrue($spm->inProgress(), 'spm-fls-in-progress');
        $this->assertTrue($spm->visible($this->user), 'spm-fls-read-user');
        $this->assertTrue($spm->visible($this->fls), 'spm-fls-read-fls');
        $this->assertTrue($spm->visible($this->mod), 'spm-fls-read-mod');
        $this->assertTrue($spm->visible($this->sysop), 'spm-fls-read-sysop');
        $this->assertTrue($spm->isUnread(), 'spm-fls-unread');
        $this->assertFalse($spm->isResolved(), 'spm-fls-unresolved');
        $this->assertFalse($spm->unassigned(), 'spm-fls-unassigned');

        $this->assertEquals($spm->id(), $this->spMan->findById($spm->id())?->id(), 'spm-fls-find');
        $list = $this->spMan->findAllByUserId($this->user->id());
        $this->assertCount(1, $list, 'spm-user-list-total');
        $this->assertEquals($spm->subject(), $list[0]->subject(), 'spm-user-list-first');

        $this->assertEquals(1, $spm->reply($this->fls, 'fls reply'), 'spm-user-fls-reply');
        $this->assertTrue($spm->isUnread(), 'spm-fls-unread');

        $this->assertEquals(1, $spm->assign($this->fls, $this->fls), 'spm-user-assign-fls-by-fls');
        $this->assertEquals($this->fls->effectiveClass(), $spm->classLevel(), 'spm-fls-classlevel-fls');
        // FIXME: Kill internal cache
        // $this->assertFalse($spm->unassigned(), 'spm-fls-assigned');

        $this->assertEquals(1, $spm->assign($this->mod, $this->fls), 'spm-user-assign-mod-by-fls');
        $this->assertEquals(900, $spm->classLevel(), 'spm-fls-classlevel-mod');
        $this->assertTrue($spm->visible($this->user), 'spm-mod-read-user');
        $this->assertFalse($spm->visible($this->fls), 'spm-mod-read-fls');
        $this->assertTrue($spm->visible($this->mod), 'spm-mod-read-mod');
        $this->assertTrue($spm->visible($this->sysop), 'spm-mod-read-sysop');

        $this->assertEquals(1, $spm->reply($this->mod, 'mod reply'), 'spm-user-mod-reply');
        $thread = $spm->thread();
        $this->assertCount(3, $thread, 'spm-thread-total');
        $this->assertEquals($this->user->id(), $thread[0]['user_id'], 'spm-thread-0');
        $this->assertEquals($this->fls->id(), $thread[1]['user_id'], 'spm-thread-1');
        $this->assertEquals($this->mod->id(), $thread[2]['user_id'], 'spm-thread-2');

        $this->assertEquals(1, $spm->resolve($this->sysop), 'spm-resolve-by-sysop');
        $this->assertFalse($spm->inProgress(), 'spm-fls-not-in-progress');
        $this->assertTrue($spm->isResolved(), 'spm-fls-is-resolved');

        $this->assertEquals(0, $this->spMan->countByStatus($this->user, ['Open']), 'spm-user-status-open');
        $this->assertEquals(1, $this->spMan->countByStatus($this->sysop, ['Resolved']), 'spm-user-status-resolved');

        $this->assertEquals(1, $spm->unresolve($this->sysop), 'spm-unresolve');
        $this->assertTrue($spm->inProgress(), 'spm-fls-unresolved-in-progress');
        $this->assertFalse($spm->isResolved(), 'spm-fls-unresolved-is-unresolved');

        $this->assertEquals(1, $spm->assign($this->sysop, $this->mod), 'spm-user-assign-sysop-by-mod');
        $this->assertTrue($spm->visible($this->user), 'spm-sysop-read-user');
        $this->assertFalse($spm->visible($this->fls), 'spm-sysop-read-fls');
        $this->assertFalse($spm->visible($this->mod), 'spm-sysop-read-mod');
        $this->assertTrue($spm->visible($this->sysop), 'spm-sysop-read-mod');
        $spm->resolve($this->sysop);
    }

    public function testSysop(): void {
        $spm = $this->spMan->create($this->fls, 1000, 'for sysop', 'message handled by SYSOP');
        $this->assertEquals(1000, $spm->classLevel(), 'spm-sysop-classlevel-sysop');
        $this->assertEquals('Sysop', $spm->userclassName(), 'spm-sysop-userclass');
        $this->assertFalse($spm->visible($this->user), 'spm-sysop-read-user');
        $this->assertTrue($spm->visible($this->fls), 'spm-sysop-read-fls');
        $this->assertFalse($spm->visible($this->mod), 'spm-sysop-read-mod');
        $this->assertTrue($spm->visible($this->sysop), 'spm-sysop-read-sysop');
        $this->assertEquals(1, $this->spMan->countAtLevel($this->sysop, ['Unanswered']), 'spm-sysop-unanswered');
    }

    public function testMany(): void {
        $list = [
            $this->spMan->create($this->user, 0, 'for fls', 'message handled by fls'),
            $this->spMan->create($this->user, 0, 'for fls', 'message handled by fls'),
            $this->spMan->create($this->user, 0, 'for fls', 'message handled by fls'),
            $this->spMan->create($this->user, 0, 'for fls', 'message handled by fls'),
        ];
        foreach ($list as $spm) {
            $spm->assign($this->fls, $this->sysop);
        }
        $total = count($list);
        $this->assertCount(0, $this->spMan->findAllByUserId($this->fls->id()), 'spm-many-assigned');
        $this->assertEquals(0, $this->spMan->countByStatus($this->user, ['Unanswered']), 'spm-many-user-unanswered');
        $this->assertEquals($total, $this->spMan->countByStatus($this->fls, ['Unanswered']), 'spm-many-fls-unanswered');

        $this->assertEquals($total, $this->spMan->countAtLevel($this->fls, ['Unanswered']), 'spm-many-at-level');
        $list[1]->resolve($this->fls);
        $this->assertEquals($total - 1, $this->spMan->countByStatus($this->fls, ['Unanswered']), 'spm-many-fls-less');
    }

    public function testCommonAnswer(): void {
        $this->assertCount(0, $this->spMan->commonAnswerList(), 'spm-common-0');
        $answer = 'because we can';
        $first = $this->spMan->createCommonAnswer('why', $answer);
        $this->assertGreaterThan(0, $first, 'spm-common-add-1');
        $this->assertEquals($answer, $this->spMan->commonAnswer($first), 'spm-common-get');

        $second = $this->spMan->createCommonAnswer('why not', 'because we cannot');
        $this->assertEquals($first + 1, $second, 'spm-common-add-2');

        $third = $this->spMan->createCommonAnswer('third', 'third common answer');
        $this->assertEquals($first + 2, $third, 'spm-common-add-3');
        $this->assertEquals(1, $this->spMan->modifyCommonAnswer($third, 'third', 'because we might'), 'spm-common-modify');
        $this->assertCount(3, $this->spMan->commonAnswerList(), 'spm-common-list');

        $this->assertEquals(1, $this->spMan->removeCommonAnswer($second), 'spm-common-remove');
        $this->assertCount(2, $this->spMan->commonAnswerList(), 'spm-common-list');
    }
}
