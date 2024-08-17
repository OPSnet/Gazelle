<?php

use PHPUnit\Framework\TestCase;

class RenderTGroupTest extends TestCase {
    public function testRequest(): void {
        $tgroupId = (int)Gazelle\DB::DB()->scalar('SELECT ID from torrents_group');
        if (!$tgroupId) {
            $this->assertTrue(true, 'skipped (no tgroup with open requests)');
            return;
        }

        $reqMan = new Gazelle\Manager\Request();
        $tgMan  = new Gazelle\Manager\TGroup();
        $this->assertIsString(
            Gazelle\Util\Twig::factory()->render('request/torrent.twig', [
                'list' => $reqMan->findByTGroup($tgMan->findById($tgroupId))
            ]),
            'render-tgroup-request-list'
        );
    }
}
