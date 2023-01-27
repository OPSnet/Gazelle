<?php

use \PHPUnit\Framework\TestCase;

require_once(__DIR__ . '/../../lib/bootstrap.php');

class TGroupTest extends TestCase {
    protected \Gazelle\Manager\TGroup  $manager;

    public function setUp(): void {
        $this->manager = new \Gazelle\Manager\TGroup;
    }

    public function tearDown(): void {}

    public function testRequest() {
        global $DB;
        $tgroupId = $DB->scalar('SELECT ID from torrents_group');
        if ($tgroupId) {
            $tgroup = $this->manager->findById($tgroupId);
            $this->assertInstanceOf('\\Gazelle\\TGroup', $tgroup, 'tgroup-find-by-id');
        } else {
            $this->assertTrue(true, 'skipped');
        }
    }
}
