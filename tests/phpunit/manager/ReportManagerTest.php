<?php

namespace Gazelle;

use PHPUnit\Framework\TestCase;

class ReportManagerTest extends TestCase {
    protected array $reportList = [];
    protected array $userList   = [];
    protected Collage $collage;
    protected Request $request;

    public function setUp(): void {
        $this->userList = [
            \GazelleUnitTest\Helper::makeUser('report.' . randomString(10), 'report', enable: true, clearInbox: true),
            \GazelleUnitTest\Helper::makeUser('report.' . randomString(10), 'report', enable: true, clearInbox: true),
        ];
    }

    public function tearDown(): void {
        if (isset($this->collage)) {
            $this->collage->hardRemove();
        }
        if (isset($this->request)) {
            $this->request->remove();
        }
        foreach ($this->reportList as $report) {
            $report->remove();
        }
        foreach ($this->userList as $user) {
            $user->remove();
        }
    }

    public function testReportCollage(): void {
        $this->collage = (new Manager\Collage())->create(
            user:        $this->userList[0],
            categoryId:  2,
            name:        'phpunit collage report ' . randomString(20),
            description: 'phpunit collage report description',
            tagList:     'disco funk metal',
        );
        $manager = new Manager\Report(new Manager\User());
        $report = $manager->create($this->userList[1], $this->collage->id(), 'collage', 'phpunit collage report');
        $this->reportList[] = $report;
        $this->assertEquals("phpunit collage report", $report->reason(), 'collage-report-reason');
        $this->assertEquals($this->collage->id(), $report->subjectId(), 'collage-report-subject-id');
        $this->assertEquals(1, $report->resolve($this->userList[0]));

        $reportSubject = new Report\Collage($report->id(), $this->collage);
        $this->assertFileExists(TEMPLATE_PATH . $reportSubject->template(), 'collage-report-template');
        $this->assertStringContainsString($this->collage->url(), $reportSubject->bbLink(), 'collage-report-bblink');
        $this->assertEquals('Collage Report: ', $reportSubject->titlePrefix(), 'collage-report-title-prefix');
        $this->assertEquals($this->collage->name(), $reportSubject->title(), 'collage-report-title');
    }

    public function testReportRequest(): void {
        $this->request = (new Manager\Request())->create(
            user:            $this->userList[1],
            bounty:          REQUEST_MIN * 1024 * 1024,
            categoryId:      (new Manager\Category())->findIdByName('Comics'),
            year:            (int)date('Y'),
            title:           'phpunit request report',
            image:           '',
            description:     'This is a unit test description',
            recordLabel:     'Unitest Artists',
            catalogueNumber: 'UA-7890',
            releaseType:     1,
            encodingList:    'Lossless',
            formatList:      'FLAC',
            mediaList:       'WEB',
            checksum:        false,
            logCue:          '',
            oclc:            '',
        );

        $manager = new Manager\Report(new Manager\User());
        $initial = $manager->remainingTotal();

        $report = $manager->create($this->userList[1], $this->request->id(), 'request', 'phpunit report');
        $this->reportList[] = $report;

        $this->assertEquals($initial + 1, $manager->remainingTotal(), 'request-report-one-more');
        $this->assertEquals('New', $report->status(), 'request-report-status-new');
        $this->assertEquals('phpunit report', $report->reason(), 'request-report-reason');
        $this->assertEquals($this->userList[1]->id(), $report->reporter()?->id(), 'request-report-reporter-id');
        $this->assertEquals('request', $report->subjectType(), 'request-report-subject-type');
        $this->assertEquals($this->request->id(), $report->subjectId(), 'request-report-subject-id');
        $this->assertEquals(
            "<a href=\"reports.php?id={$report->id()}#report{$report->id()}\">Report #{$report->id()}</a>",
            $report->link(),
            'request-report-link'
        );
        $this->assertNull($report->notes(), 'request-no-notes-yet');
        $this->assertNull($report->claimer(), 'request-report-no-claimer');
        $this->assertNull($report->resolver(), 'request-report-no-resolver');
        $this->assertNull($report->resolved(), 'request-report-not-resolved-date');
        $this->assertFalse($report->isClaimed(), 'request-report-not-yet-claimed');

        // report specifics
        $reqReport = new Report\Request($report->id(), $this->request);
        $this->assertFileExists(TEMPLATE_PATH . $reqReport->template(), 'request-report-template');
        $this->assertStringContainsString($this->request->url(), $reqReport->bbLink(), 'request-report-bblink');
        $this->assertEquals('Request Report: ', $reqReport->titlePrefix(), 'request-report-title-prefix');
        $this->assertEquals($this->request->title(), $reqReport->title(), 'request-report-not-an-update-title');
        $this->assertTrue($reqReport->needReason(), 'request-report-not-an-update');

        // note
        $note = "abc<br />def<br />ghi";
        $report->addNote($note);
        $this->assertEquals("abc\ndef\nghi", $report->notes(), 'request-report-notes');

        // claim
        $this->assertEquals(1, $report->claim($this->userList[0]), 'request-report-claim');
        $this->assertTrue($report->isClaimed(), 'request-report-is-claimed');
        $this->assertEquals('InProgress', $report->flush()->status(), 'request-report-in-progress');
        $claimer = $report->claimer();
        $this->assertNotNull($claimer, 'request-report-has-claimer');
        $this->assertEquals($this->userList[0]->id(), $claimer->id(), 'request-report-claimer-id');
        $this->assertEquals(1, $report->claim(null), 'request-report-unclaim');
        $this->assertFalse($report->isClaimed(), 'request-report-is-unclaimed');

        // search
        $this->assertCount(
            1,
            (new Search\Report())->setId($report->id())->page(2, 0),
            'request-report-search-id'
        );
        $this->assertEquals(
            1,
            (new Search\Report())->setStatus(['InProgress'])->total(),
            'request-report-search-in-progress-total'
        );
        $this->assertEquals(
            0,
            (new Search\Report())->setStatus(['InProgress'])->restrictForumMod()->total(),
            'request-report-search-fmod-in-progress-total'
        );

        $search = new Search\Report();
        $total  = $search->setStatus(['InProgress'])->total();
        $page   = $search->page($total, 0);
        $this->assertEquals($total, count($page), 'request-report-page-list');
        $this->assertEquals($report->id(), $page[0], 'request-report-page-id');

        // resolve
        $this->assertEquals(1, $report->resolve($this->userList[0]), 'request-report-claim');
        $this->assertNotNull($report->resolved(), 'request-report-resolved-date');
        $this->assertEquals('Resolved', $report->status(), 'request-report-resolved-status');
        $resolver = $report->resolver();
        $this->assertNotNull($resolver, 'request-report-has-resolver');
        $this->assertEquals($this->userList[0]->id(), $resolver->id(), 'request-report-resolver-id');
        $this->assertEquals($initial, $manager->remainingTotal(), 'request-report-initial-total');
    }

    public function testReportUser(): void {
        $manager = new Manager\Report(new Manager\User());
        $report = $manager->create($this->userList[0], $this->userList[1]->id(), 'user', 'phpunit user report');
        $this->reportList[] = $report;

        $this->assertInstanceOf(Report::class, $report, 'report-user-create');
        $this->assertEquals(
            "<a href=\"{$report->url()}\">Report #{$report->id()}</a>",
            $report->link(),
            'report-link'
        );
        $this->assertEquals(
            "reports.php?id={$report->id()}#report{$report->id()}",
            $report->location(),
            'report-location'
        );
        $this->assertEquals('phpunit user report', $report->reason(), 'report-reason');
        $this->assertEquals('New', $report->status(), 'report-new-status');
        $this->assertEquals($this->userList[1]->id(), $report->subjectId(), 'report-subject-id');
        $this->assertEquals('user', $report->subjectType(), 'report-subject-type');
        $this->assertEquals($this->userList[0]->id(), $report->reporter()->id(), 'report-reporter');
        $this->assertFalse($report->isClaimed(), 'report-is-not-claimed');
        $this->assertNull($report->resolved(), 'report-not-resolved');
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $report->created(), 'report-created');

        $this->assertNull($report->notes(), 'report-no-notes');
        $report->addNote('phpunit add note');
        $this->assertEquals('phpunit add note', $report->notes(), 'report-add-notes');

        $this->assertEquals($report->id(), $manager->findById($report->id())->id(), 'report-user-find');

        $reportSubject = new Report\User($report->id(), $this->userList[1]);
        $this->assertFileExists(TEMPLATE_PATH . $reportSubject->template(), 'user-report-template');
        $this->assertStringContainsString($this->userList[1]->username(), $reportSubject->bbLink(), 'user-report-bblink');
        $this->assertEquals('User Report: ', $reportSubject->titlePrefix(), 'user-report-title-prefix');
        $this->assertEquals($this->userList[1]->username(), $reportSubject->title(), 'user-report-title');
    }

    public function testDecorate(): void {
        $manager = new Manager\Report(new Manager\User());

        $this->collage = (new Manager\Collage())->create(
            user:        $this->userList[0],
            categoryId:  2,
            name:        'phpunit collage report ' . randomString(20),
            description: 'phpunit collage report description',
            tagList:     'disco funk metal',
        );
        $report = $manager->create($this->userList[1], $this->collage->id(), 'collage', 'phpunit collage report');
        $this->reportList[] = $report;
        $report = $manager->create($this->userList[0], $this->userList[1]->id(), 'user', 'phpunit user report');
        $this->reportList[] = $report;

        $list = $manager->decorate(
            array_map(fn($r) => $r->id(), $this->reportList),
            new Manager\Collage(),
            new Manager\Comment(),
            new Manager\ForumThread(),
            new Manager\ForumPost(),
            new Manager\Request(),
        );
        $this->assertCount(2, $list, 'report-decorate-list');
        $this->assertEquals('collage', $list[0]['label'], 'report-list-label');
        $this->assertEquals($this->collage->id(), $list[0]['subject']->id(), 'report-list-subject-id');
        $this->assertNull($list[0]['context'], 'report-list-collage-context-null');
    }
}
