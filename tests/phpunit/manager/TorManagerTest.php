<?php

use PHPUnit\Framework\TestCase;

require_once(__DIR__ . '/../../../lib/bootstrap.php');

class TorManagerTest extends TestCase {
    public function testTor(): void {
        $manager = new \Gazelle\Manager\Tor;
        $list = array_map(fn($n) => $n['ipv4'], $manager->exitNodeList());
        $this->assertIsArray($list, 'tornode-has-list');
        $this->assertFalse($manager->isExitNode('0.0.0.0'), 'tornode-0000-exit-node');

        $this->assertFalse($manager->isExitNode('169.254.100.110'), 'tornode-not-exit-node');
        $this->assertEquals(
            2,
            $manager->add(implode(' ', $list) . ' 169.254.100.110 169.254.110.120'),
            'tornode-add-two'
        );
        $this->assertTrue($manager->isExitNode('169.254.100.110'), 'tornode-is-exit-node');

        if ($list) {
            // reset
            $manager->add(implode(' ', $list));
        }
    }
}
