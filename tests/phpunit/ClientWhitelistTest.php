<?php

namespace Gazelle;

use PHPUnit\Framework\TestCase;

class ClientWhitelistTest extends TestCase {
    public function testWhitelist(): void {
        $manager = new Manager\ClientWhitelist();
        $initial = $manager->list();
        $this->assertGreaterThanOrEqual(0, count($initial), 'cwl-initial');

        $id = $manager->create('&pu', 'PHPUnit');
        $this->assertGreaterThan(0, $id, 'cwl-id');
        $this->assertCount(count($initial) + 1, $manager->list(), 'cwl-new-list');
        $this->assertSame('&pu', $manager->peerId($id), 'cwl-peerid');

        $this->assertSame('&pu', $manager->modify($id, '&puA', 'PHPUnit-A'), 'cwl-modify');
        $this->assertSame(1, $manager->remove($id), 'cwl-remove');
    }
}
