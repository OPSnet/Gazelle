<?php

namespace Gazelle;

use PHPUnit\Framework\TestCase;

class StaffGroupTest extends TestCase {
    public function testStaffGroupCreate(): void {
        $manager = new Manager\StaffGroup();
        $initial = $manager->groupList();
        $sg = $manager->create(1234, 'phpunit');

        $staffList = $manager->groupList();
        $this->assertCount(
            count($initial) + 1,
            $staffList,
            'staff-group-added'
        );

        $find = $manager->findById($sg->id);
        $this->assertInstanceOf(StaffGroup::class, $find, 'staff-group-find');
        $this->assertNull($manager->findById($sg->id + 1), 'staff-group-null');

        $this->assertSame(1, $find->remove(), 'staff-group-removed');
        $this->assertCount(
            count($initial),
            $manager->groupList(),
            'staff-group-restored',
        );
    }
}
