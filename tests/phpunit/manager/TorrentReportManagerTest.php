<?php

use PHPUnit\Framework\TestCase;
use Gazelle\Enum\TorrentFlag;

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

    public function testWorkflowReport(): void {
        $torMan = new \Gazelle\Manager\Torrent();
        $torrent = $torMan->findById($this->tgroup->torrentIdList()[0]);
        $this->assertInstanceOf(\Gazelle\Torrent::class, $torrent, 'report-torrent-is-torrent');
        $report = (new \Gazelle\Manager\Torrent\Report($torMan))->create(
            torrent:     $torrent,
            user:        $this->userList[1],
            reportType:  (new \Gazelle\Manager\Torrent\ReportType())->findByName('other'),
            reason:      'phpunit other report',
            otherIdList: '123 234',
            irc:         new Gazelle\Util\Irc(),
        );

        $this->assertTrue(Helper::recentDate($report->created()), 'torrent-report-created');
        $this->assertCount(0, $report->externalLink(), 'torrent-report-external-link');
        $this->assertCount(0, $report->trackList(), 'torrent-report-track-list');
        $this->assertStringEndsWith("id={$report->id()}", $report->location(), 'torrent-report-location');
        $this->assertEquals('phpunit other report', $report->reason(), 'torrent-report-reason');
        $this->assertEquals([123, 234], $report->otherIdList(), 'torrent-report-other-id-list');
        $this->assertEquals($this->userList[1]->id(), $report->reporterId(), 'torrent-report-reporter-id');
        $this->assertEquals('New', $report->status(), 'torrent-report-report-status');
        $this->assertEquals('Other', $report->reportType()->name(), 'torrent-report-report-type-name');
        $this->assertEquals('other', $report->type(), 'torrent-report-name');
        $this->assertEquals($this->tgroup->torrentIdList()[0], $report->torrentId(), 'torrent-report-torrent-id');
        $this->assertEquals($this->tgroup->torrentIdList()[0], $report->torrent()->id(), 'torrent-report-torrent-object');

        $this->assertEquals(1, $report->claim($this->userList[0]), 'torrent-report-claim');
        $this->assertEquals('InProgress', $report->status(), 'torrent-report-report-open-status');
        $this->assertEquals(1, $report->addTorrentFlag(TorrentFlag::badFile, $this->userList[0]), 'torrent-report-add-flag');
        $this->assertEquals(1, $report->modifyComment('phpunit'), 'torrent-report-modify-comment');
        $this->assertEquals('phpunit', $report->comment(), 'torrent-report-final-comment');
        $this->assertEquals(1, $report->unclaim(), 'torrent-report-claim');

        $this->assertEquals(1, $report->resolve('phpunit resolve'), 'torrent-report-resolve');
        $this->assertEquals('phpunit resolve', $report->comment(), 'torrent-report-resolved-comment');
        $this->assertEquals('', $report->message(), 'torrent-report-message');
        $this->assertEquals(1, $report->finalize('phpunit final message', 'phpunit final comment'), 'torrent-report-finalize');
        $this->assertEquals('phpunit final comment', $report->comment(), 'torrent-report-final-comment');
        $this->assertEquals('phpunit final message', $report->message(), 'torrent-report-final-message');
        $this->assertEquals('Resolved', $report->status(), 'torrent-report-report-resolved-status');
    }

    public function testModeratorResolve(): void {
        $torMan = new \Gazelle\Manager\Torrent();
        $torrent = $torMan->findById($this->tgroup->torrentIdList()[0]);
        $this->assertInstanceOf(\Gazelle\Torrent::class, $torrent, 'report-torrent-is-torrent');
        $report = (new \Gazelle\Manager\Torrent\Report($torMan))->create(
            torrent:     $torrent,
            user:        $this->userList[1],
            reportType:  (new \Gazelle\Manager\Torrent\ReportType())->findByName('other'),
            reason:      'phpunit other report',
            otherIdList: '123 234',
            irc:         new Gazelle\Util\Irc(),
        );
        $this->assertEquals(1, $report->moderatorResolve($this->userList[0], 'phpunit moderator resolve'), 'torrent-report-moderator-resolve');
        $this->assertEquals('phpunit moderator resolve', $report->comment(), 'torrent-report-final-comment');
    }

    public function testModifyReport(): void {
        $reportType = (new \Gazelle\Manager\Torrent\ReportType())->findByName('other');
        $reportType->setChangeset($this->userList[0], [['field' => 'is_admin', 'old' => $reportType->isAdmin(), 'new' => 0]]);
        $this->assertFalse($reportType->setField('is_admin', false)->modify(), 'torrent-report-modify');
    }

    public function testUrgentReport(): void {
        $torMan = new \Gazelle\Manager\Torrent();
        $torMan->setViewer($this->userList[0]);
        $type = (new \Gazelle\Manager\Torrent\ReportType())->findByName('urgent');
        $this->assertInstanceOf(\Gazelle\Torrent\ReportType::class, $type, 'torrent-report-instance-urgent');

        $torrentId = $this->tgroup->torrentIdList()[0];
        $torrent = $torMan->findById($torrentId);
        $report = (new \Gazelle\Manager\Torrent\Report($torMan))->create(
            torrent:     $torrent,
            user:        $this->userList[1],
            reportType:  $type,
            reason:      'phpunit urgent report',
            otherIdList: '',
            irc:         new Gazelle\Util\Irc(),
        );
        $this->assertEquals([], $torrent->labelList($this->userList[0]), 'uploader-report-label');

        $urgent = $torMan->findById($torrentId);
        $labelList = $urgent->labelList($this->userList[1]);
        $this->assertCount(1, $labelList, 'reporter-report-label');
        $this->assertStringContainsString('Reported', $labelList[0], 'reporter-report-urgent');
    }
}
