<?php

namespace Gazelle;

use PHPUnit\Framework\TestCase;

class RenderTGroupTest extends TestCase {
    public function testRequest(): void {
        $tgroupId = (int)DB::DB()->scalar('SELECT ID from torrents_group');
        if (!$tgroupId) {
            $this->assertTrue(true, 'skipped (no tgroup with open requests)');
            return;
        }

        $reqMan = new Manager\Request();
        $tgMan  = new Manager\TGroup();
        $this->assertIsString(
            Util\Twig::factory()->render('request/torrent.twig', [
                'list' => $reqMan->findByTGroup($tgMan->findById($tgroupId))
            ]),
            'render-tgroup-request-list'
        );
    }
}
