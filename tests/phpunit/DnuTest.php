<?php

use PHPUnit\Framework\TestCase;

require_once(__DIR__ . '/../../lib/bootstrap.php');
require_once(__DIR__ . '/../helper.php');

class DnuTest extends TestCase {
    protected Gazelle\User $user;

    public function setup(): void {
        $this->user = Helper::makeUser('dnu.' . randomString(10), 'dnu');
    }

    public function tearDown(): void {
        $this->user->remove();
    }

    public function testDnu(): void {
        $manager = new Gazelle\Manager\DNU();

        $initial = $manager->dnuList();
        $dnu = $manager->create('phpunit ' . randomString(10), 'phpunit description', $this->user);
        $this->assertCount(count($initial) + 1, $manager->dnuList(), 'dnu-create');

        $this->assertEquals(
            1,
            $manager->modify(
                id:      $dnu,
                name:    'phpunit new ' . randomString(10),
                comment: 'phpunit modified description',
                user:    $this->user,
            ),
            'dnu-modify'
        );
        $this->assertIsString($manager->latest(), 'dnu-latest'); // validate SQL
        $this->assertTrue($manager->hasNewForUser($this->user), 'dnu-user-latest');

        $this->assertEquals(
            0,
            $manager->modify(
                id:      $dnu + 1,
                name:    'fail',
                comment: 'fail',
                user:    $this->user,
            ),
            'dnu-fail-modify'
        );
        $this->assertEquals(1, $manager->remove($dnu), 'dnu-remove');
        $this->assertEquals(0, $manager->remove($dnu), 'dnu-fail-remove');
    }

    public function testReorder(): void {
        $manager = new Gazelle\Manager\DNU();
        $idList = array_map(fn ($x) => $x['id'], $manager->dnuList());

        $first  = $manager->create('phpunit first ' . randomString(10), 'phpunit description', $this->user);
        $second = $manager->create('phpunit second ' . randomString(10), 'phpunit description', $this->user);

        // This may fail when run locally because all the Sequence values could
        // be 9999 if the list has never been reordered, so all will change.
        // If that happens, run again.  We really only want to check that the
        // SQL is correct.
        $reorder = $manager->reorder([...$idList, $second, $first]);
        $this->assertEquals(2, $reorder, 'dnu-reorder');

        $manager->remove($first);
        $manager->remove($second);
    }
}
