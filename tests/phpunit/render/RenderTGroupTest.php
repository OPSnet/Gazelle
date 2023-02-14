<?php

use \PHPUnit\Framework\TestCase;

require_once(__DIR__ . '/../../../lib/bootstrap.php');

class RenderTGroupTest extends TestCase {
    public function testRequest() {
        global $DB;
        $tgroupId = $DB->scalar('SELECT ID from torrents_group');
        if (!$tgroupId) {
            $this->assertTrue(true, 'skipped (no tgroup with open requests)');
            return;
        }

        $reqMan = new Gazelle\Manager\Request;
        $tgMan  = new Gazelle\Manager\TGroup;
        $this->assertIsString(
            Gazelle\Util\Twig::factory()->render('torrent/request.twig', [
                'list' => $reqMan->findByTGroup($tgMan->findById($tgroupId))
            ]),
            'render-tgroup-request-list'
        );
    }
}
