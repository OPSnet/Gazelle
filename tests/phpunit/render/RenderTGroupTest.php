<?php

use \PHPUnit\Framework\TestCase;

require_once(__DIR__ . '/../../../lib/bootstrap.php');

class RenderTGroupTest extends TestCase {
    protected \Gazelle\Manager\Request $reqMan;
    protected \Gazelle\Manager\TGroup  $tgMan;

    public function setUp(): void {
        $this->reqMan = new \Gazelle\Manager\Request;
        $this->tgMan  = new \Gazelle\Manager\TGroup;
    }

    public function tearDown(): void {}

    public function testRequest() {
        global $DB, $Twig;
        $tgroupId = $DB->scalar('SELECT ID from torrents_group');
        if (!$tgroupId) {
            $this->assertTrue(true, 'skipped (no tgroup with open requests)');
            return;
        }
        $this->assertIsString(
            $Twig->render('torrent/request.twig', [
                'list' => $this->reqMan->findByTGroup($this->tgMan->findById($tgroupId))
            ]),
            'render-tgroup-request-list'
        );
    }
}
