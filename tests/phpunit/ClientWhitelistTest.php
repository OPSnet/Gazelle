<?php

namespace Gazelle;

use PHPUnit\Framework\TestCase;

class ClientWhitelistTest extends TestCase {
    public function testWhitelist(): void {
        $manager = new Manager\ClientWhitelist();
        $initial = $manager->list();
        $this->assertIsArray($initial, 'cwl-initial');

        $id = $manager->create('&pu', 'PHPUnit');
        $this->assertGreaterThan(0, $id, 'cwl-id');
        $this->assertCount(count($initial) + 1, $manager->list(), 'cwl-new-list');
        $this->assertEquals('&pu', $manager->peerId($id), 'cwl-peerid');

        $this->assertEquals('&pu', $manager->modify($id, '&puA', 'PHPUnit-A'), 'cwl-modify');
        $this->assertEquals(1, $manager->remove($id), 'cwl-remove');
    }
}
