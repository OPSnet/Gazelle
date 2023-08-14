<?php

use \PHPUnit\Framework\TestCase;

require_once(__DIR__ . '/../../../lib/bootstrap.php');
require_once(__DIR__ . '/../../helper.php');

class TorrentReportManagerTest extends TestCase {
    protected array $userList   = [];
    protected \Gazelle\TGroup $tgroup;

    public function setUp(): void {
        $this->userList = [
            Helper::makeUser('reportg.' . randomString(10), 'reportg'),
            Helper::makeUser('reportg.' . randomString(10), 'reportg'),
        ];

        // create a torrent group
        $this->tgroup = Helper::makeTGroupMusic(
            name:       'phpunit torrent report ' . randomString(6),
            artistName: [[ARTIST_MAIN], ['Report Dog ' . randomString(12)]],
            tagName:    ['electronic'],
            user:       $this->userList[0],
        );

        Helper::makeTorrentMusic(
            tgroup: $this->tgroup,
            user:   $this->userList[0],
            title:  'torrent report',
        );
    }

    public function tearDown(): void {
        Helper::removeTGroup($this->tgroup, $this->userList[0]);
        foreach ($this->userList as $user) {
            $user->remove();
        }
    }

    public function testUrgentReport(): void {
        $torMan = new \Gazelle\Manager\Torrent;
        $torMan->setViewer($this->userList[0]);
        $type = (new \Gazelle\Manager\Torrent\ReportType)->findByName('urgent');
        $this->assertInstanceOf(\Gazelle\Torrent\ReportType::class, $type, 'torrent-report-instance-urgent');

        $torrentId = $this->tgroup->torrentIdList()[0];
        $torrent = $torMan->findById($torrentId);
        $report = (new \Gazelle\Manager\Torrent\Report($torMan))->create(
            torrent:     $torrent,
            user:        $this->userList[1],
            reportType:  $type,
            reason:      'phpunit urgent report',
            otherIdList: '',
        );
        $this->assertEquals([], $torrent->labelList($this->userList[0]), 'uploader-report-label');

        $urgent = $torMan->findById($torrentId);
        $labelList = $urgent->labelList($this->userList[1]);
        $this->assertCount(1, $labelList, 'reporter-report-label');
        $this->assertStringContainsString('Reported', $labelList[0], 'reporter-report-urgent');
    }
}
