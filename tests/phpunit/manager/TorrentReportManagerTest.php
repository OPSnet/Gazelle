<?php

namespace Gazelle;

use PHPUnit\Framework\TestCase;
use GazelleUnitTest\Helper;
use Gazelle\Enum\TorrentFlag;

class TorrentReportManagerTest extends TestCase {
    protected array $userList   = [];
    protected TGroup $tgroup;

    public function setUp(): void {
        $this->userList = [
            Helper::makeUser('reportg.' . randomString(10), 'reportg'),
            Helper::makeUser('reportg.' . randomString(10), 'reportg'),
        ];
        $this->userList[0]->requestContext()->setViewer($this->userList[0]);

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
        foreach ($this->userList as $user) {
            DB::DB()->prepared_query("
                DELETE FROM torrent_report_configuration_log WHERE user_id = ?
                ", $user->id
            );
            Helper::removeUser($user);
        }
        DB::DB()->prepared_query("
            DELETE FROM reportsv2 WHERE UserComment REGEXP '^phpunit (?:other|search|urgent) report$'
        ");
    }

    public function testWorkflowReport(): void {
        $torMan = new Manager\Torrent();
        $torrent = $torMan->findById($this->tgroup->torrentIdList()[0]);
        $this->assertInstanceOf(Torrent::class, $torrent, 'trep-workflow-found');
        $title = [
            'torrent extra 1 ' . randomString(10),
            'torrent extra 2 ' . randomString(10),
        ];
        $extra = [
            Helper::makeTorrentMusic(
                tgroup: $this->tgroup,
                user:   $this->userList[0],
                title:  $title[0],
            ),
            Helper::makeTorrentMusic(
                tgroup: $this->tgroup,
                user:   $this->userList[0],
                title:  $title[1],
            ),
        ];
        $extraIdList = [$extra[0]->id, $extra[1]->id];
        $manager     = new Manager\Torrent\Report($torMan);
        $typeManager = new Manager\Torrent\ReportType();
        $type        = $typeManager->findByName('other');
        $this->assertInstanceOf(Torrent\ReportType::class, $type, 'trep-type-found');
        $this->assertCount(8, $manager->categories(), 'trep-categories');
        $report = $manager->create(
            torrent:     $torrent,
            user:        $this->userList[1],
            reportType:  $type,
            reason:      'phpunit other report',
            otherIdList: implode(' ', $extraIdList),
            irc:         new Util\Irc(),
        );
        $this->assertTrue(
            $manager->existsRecent($torrent, $this->userList[1]),
            'trep-recent',
        );

        $this->assertInstanceOf(
            Torrent\Report::class,
            $manager->findById($report->id),
            'trep-find-by-id'
        );
        $this->assertInstanceOf(
            Torrent\Report::class,
            $manager->findNewest(),
            'trep-newest'
        );

        $this->assertTrue(Helper::recentDate($report->created()), 'trep-created');
        $this->assertCount(0, $report->externalLink(), 'trep-external-link');
        $this->assertCount(0, $report->trackList(), 'trep-track-list');
        $this->assertStringEndsWith("id={$report->id}", $report->location(), 'trep-location');
        $this->assertEquals('phpunit other report', $report->reason(), 'trep-reason');
        $this->assertEquals($this->userList[1]->id, $report->reporterId(), 'trep-reporter-id');
        $this->assertEquals('New', $report->status(), 'trep-report-status');
        $this->assertEquals('Other', $report->reportType()->name(), 'trep-report-type-name');
        $this->assertEquals('other', $report->type(), 'trep-name');
        $this->assertEquals($this->tgroup->torrentIdList()[2], $report->torrentId(), 'trep-torrent-id');
        $this->assertEquals($this->tgroup->torrentIdList()[2], $report->torrent()?->id, 'trep-torrent-object');

        $other = current(array_filter($manager->newSummary(), fn ($r) => $r['type'] === 'other'));
        $this->assertEquals(1, $other['total'], 'trep-new-summary-other');
        $this->assertEquals(
            $extraIdList,
            $report->otherIdList(),
            'trep-other-id-list'
        );
        $this->assertEquals(
            $title,
            array_map(
                fn ($t) => $t->remasterTitle(),
                $report->otherTorrentList(),
            ),
            'trep-other-torrent-list'
        );

        $this->assertEquals(1, $report->claim($this->userList[0]), 'trep-claim');
        $summary = $manager->inProgressSummary();
        $this->assertCount(1, $summary, 'trep-in-progress-total');
        $this->assertEquals($this->userList[0]->id, $summary[0]['user_id'], 'trep-in-progress-mod');
        $this->assertEquals(
            1,
            $manager->totalReportsTorrent($torrent),
            'trep-in-progress-torrent',
        );
        $this->assertEquals(
            1,
            $manager->totalReportsTGroup($torrent->group()),
            'trep-in-progress-tgroup',
        );
        $this->assertEquals(
            1,
            $manager->totalReportsUploader($this->userList[0]),
            'trep-in-progress-uploader',
        );

        $this->assertEquals('InProgress', $report->status(), 'trep-report-open-status');
        $this->assertEquals(1, $report->addTorrentFlag(TorrentFlag::badFile, $this->userList[0]), 'trep-add-flag');
        $this->assertEquals(1, $report->modifyComment('phpunit'), 'trep-modify-comment');
        $this->assertEquals('phpunit', $report->comment(), 'trep-final-comment');
        $this->assertEquals(1, $report->unclaim(), 'trep-claim');

        $this->assertEquals(1, $report->resolve('phpunit resolve'), 'trep-resolve');
        $this->assertEquals('phpunit resolve', $report->comment(), 'trep-resolved-comment');
        $this->assertEquals('', $report->message(), 'trep-message');
        $this->assertEquals(1, $report->finalize('phpunit final message', 'phpunit final comment'), 'trep-finalize');
        $this->assertEquals('phpunit final comment', $report->comment(), 'trep-final-comment');
        $this->assertEquals('phpunit final message', $report->message(), 'trep-final-message');
        $this->assertEquals('Resolved', $report->status(), 'trep-report-resolved-status');
    }

    public function testModeratorResolve(): void {
        $torMan = new Manager\Torrent();
        $manager = new Manager\Torrent\Report($torMan);
        $torrent = $torMan->findById($this->tgroup->torrentIdList()[0]);
        $this->assertInstanceOf(Torrent::class, $torrent, 'trep-mod-resolver-found');
        $reportType = new Manager\Torrent\ReportType()->findByName('other');
        $this->assertInstanceOf(Torrent\ReportType::class, $reportType, 'trep-report-type-search-found');
        $report = $manager->create(
            torrent:     $torrent,
            user:        $this->userList[1],
            reportType:  $reportType,
            reason:      'phpunit other report',
            otherIdList: '123 234',
            irc:         new Util\Irc(),
        );
        $this->assertEquals(1, $report->moderatorResolve($this->userList[0], 'phpunit moderator resolve'), 'trep-moderator-resolve');
        $this->assertEquals('phpunit moderator resolve', $report->comment(), 'trep-final-comment');

        $this->assertCount(1, $manager->resolvedLastDay(), 'trep-resolve-day');
        $this->assertCount(1, $manager->resolvedLastWeek(), 'trep-resolve-week');
        $this->assertCount(1, $manager->resolvedLastMonth(), 'trep-resolve-month');
        $this->assertGreaterThanOrEqual(1, $manager->resolvedSummary(), 'trep-resolve-summary');
    }

    public function testSearchTorrentReport(): void {
        $torMan = new Manager\Torrent();
        $manager = new Manager\Torrent\Report($torMan);
        $torrent = $torMan->findById($this->tgroup->torrentIdList()[0]);
        $this->assertInstanceOf(Torrent::class, $torrent, 'trep-search-found');
        $reportType = new Manager\Torrent\ReportType()->findByName('other');
        $this->assertInstanceOf(Torrent\ReportType::class, $reportType, 'trep-report-type-search-found');
        $report = $manager->create(
            torrent:     $torrent,
            user:        $this->userList[1],
            reportType:  $reportType,
            reason:      'phpunit search report',
            otherIdList: '1234 5678',
            irc:         new Util\Irc(),
        );

        $manager->setSearchFilter([
            'dt-from'     => date('Y-m-d H:i:s', time() - 10),
            'dt-until'    => date('Y-m-d H:i:s', time() + 10),
            'group'       => $torrent->groupId(),
            'reporter'    => $this->userList[1],
            'torrent'     => $torrent->id,
            'uploader'    => $this->userList[0],
            'report-type' => ['other', 'dupe'],
        ]);
        $this->assertEquals(1, $manager->searchTotal(), 'trep-search-total');
        $list = $manager->searchList(1, 0);
        $this->assertEquals($report->id, $list[0]['report_id'], 'trep-search-report');
    }

    public function testModifyReport(): void {
        $reportType = new Manager\Torrent\ReportType()->findByName('other');
        $this->assertInstanceOf(Torrent\ReportType::class, $reportType, 'report-torrent-found');
        $reportType->setChangeset($this->userList[0], [['field' => 'is_admin', 'old' => $reportType->isAdmin(), 'new' => 0]]);
        $this->assertFalse($reportType->setField('is_admin', false)->modify(), 'trep-modify');
    }

    public function testUrgentReport(): void {
        $torMan = new Manager\Torrent();
        $torrent = $torMan->findById($this->tgroup->torrentIdList()[0]);
        $this->assertInstanceOf(Torrent::class, $torrent, 'report-torrent-is-torrent');

        $type = new Manager\Torrent\ReportType()->findByName('urgent');
        $this->assertInstanceOf(Torrent\ReportType::class, $type, 'trep-instance-urgent');

        $torrentId = $this->tgroup->torrentIdList()[0];
        $torrent = $torMan->findById($torrentId);
        $this->assertInstanceOf(Torrent::class, $torrent, 'trep-torrent-find');
        new Manager\Torrent\Report($torMan)->create(
            torrent:     $torrent,
            user:        $this->userList[1],
            reportType:  $type,
            reason:      'phpunit urgent report',
            otherIdList: '',
            irc:         new Util\Irc(),
        );
        $this->assertEquals([], $torrent->labelList($this->userList[0]), 'uploader-report-label');

        $urgent = $torMan->findById($torrentId);
        $this->assertInstanceOf(Torrent::class, $urgent, 'trep-urgent-find');
        $labelList = $urgent->labelList($this->userList[1]);
        $this->assertCount(1, $labelList, 'reporter-report-label');
        $this->assertStringContainsString('Reported', $labelList[0], 'reporter-report-urgent');
    }

    public function testTorrentReportType(): void {
        $manager = new Manager\Torrent\ReportType();
        $this->assertCount(
            53,
            $manager->list(),
            'trep-type-count'
        );
        $this->assertCount(
            36,
            $manager->categoryList(CATEGORY_MUSIC),
            'trep-type-cat-count'
        );

        $dupe = $manager->findById(1);
        $this->assertInstanceOf(Torrent\ReportType::class, $dupe, 'trep-dupe-find');
        $dupe->flush(); // to trigger coverage
        $this->assertEquals(
            'Dupe',
            $dupe->name(),
            'trep-type-find-by-id',
        );
        $this->assertStringEndsWith(
            "id={$dupe->id}",
            $dupe->url(),
            'trep-type-url',
        );
        $this->assertEquals(
            'required',
            $dupe->needSitelink(),
            'trep-type-site-link',
        );
        $this->assertEquals(
            'dupe',
            $manager->findByName('Dupe')?->type(),
            'trep-type-find-by-name',
        );
        $this->assertEquals(
            1,
            $manager->findByType('dupe')?->id,
            'trep-type-find-by-type',
        );
        $this->assertEquals(
            [true, false, 0],
            $dupe->resolveOptions(),
            'trep-resolve-options',
        );

        $this->assertNull($manager->findById(0), 'trep-no-find-id');
        $this->assertNull($manager->findByName('No Such Name'), 'trep-no-find-name');
        $this->assertNull($manager->findByType('no-such-type'), 'trep-no-find-type');
    }

    public function testTorrentReportTypeHistory(): void {
        $manager = new Manager\Torrent\ReportType();
        $config  = $manager->findByType('description'); // a rarely used report type
        $this->assertEquals('Applications', $config->categoryName(), 'trep-category-name');
        $this->assertEquals(2, $config->categoryId(), 'trep-category-id');
        $initial = count($config->history());

        $old = $config->field('explanation');
        $add = "phpuntit " . randomString(8);
        $changeset = [[
            'field' => 'explanation',
            'old'   => $old,
            'new'   => "$old\n$add",
        ]];
        $this->assertTrue(
            $config->setChangeset($this->userList[0], $changeset)->modify(),
            'trep-change',
        );
        $this->assertEquals(
            $initial + 1,
            count($config->history()),
            'trep-history'
        );
        $this->assertStringContainsString(
            $add,
            $config->explanation(),
            'trep-explanation',
        );
    }
}
