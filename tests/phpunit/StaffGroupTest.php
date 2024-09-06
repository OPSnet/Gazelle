<?php

namespace Gazelle;

use PHPUnit\Framework\TestCase;

class StaffGroupTest extends TestCase {
    public function testStaffGroupCreate(): void {
        $manager = new Manager\StaffGroup();
        $initial = $manager->groupList();

        $sg = $manager->create(1234, 'phpunit');
        $this->assertInstanceOf(StaffGroup::class, $sg, 'staff-group-create');

        $staffList = $manager->groupList();
        $this->assertEquals(count($initial) + 1, count($staffList), 'staff-group-added');

        $find = $manager->findById($sg->id());
        $this->assertInstanceOf(StaffGroup::class, $find, 'staff-group-find');
        $this->assertNull($manager->findById($sg->id() + 1), 'staff-group-null');

        $this->assertEquals(1, $find->remove(), 'staff-group-removed');
        $this->assertEquals(count($initial), count($manager->groupList()), 'staff-group-restored');
    }
}
