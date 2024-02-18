<?php

use PHPUnit\Framework\TestCase;

require_once(__DIR__ . '/../../lib/bootstrap.php');
require_once(__DIR__ . '/../helper.php');

class SearchReportTest extends TestCase {
    protected array $reportList = [];
    protected array $userList   = [];
    protected \Gazelle\Collage $collage;
    protected \Gazelle\Request $request;

    public function setUp(): void {
        $this->userList = [
            Helper::makeUser('searchrep.' . randomString(10), 'searchrep', enable: true, clearInbox: true),
            Helper::makeUser('searchrep.' . randomString(10), 'searchrep', enable: true, clearInbox: true),
        ];

        $this->collage = (new \Gazelle\Manager\Collage())->create(
            user:        $this->userList[0],
            categoryId:  2,
            name:        'phpunit search report ' . randomString(20),
            description: 'phpunit search report description',
            tagList:     'disco funk metal',
            logger:      new \Gazelle\Log(),
        );

        $this->request = (new \Gazelle\Manager\Request())->create(
            user:            $this->userList[1],
            bounty:          REQUEST_MIN * 1024 * 1024,
            categoryId:      (new \Gazelle\Manager\Category())->findIdByName('Music'),
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

        $manager = new \Gazelle\Manager\Report(new \Gazelle\Manager\User());
        $this->reportList['collage'] = $manager->create($this->userList[0], $this->collage->id(), 'collage', 'phpunit search collage report');
        sleep(1);
        $this->reportList['request'] = $manager->create($this->userList[0], $this->request->id(), 'request', 'phpunit search request report');
        sleep(1);
        $this->reportList['user'] = $manager->create($this->userList[0], $this->userList[1]->id(), 'user', 'phpunit search user report');
    }

    public function tearDown(): void {
        $this->collage->hardRemove();
        $this->request->remove();
        foreach ($this->reportList as $report) {
            $report->remove();
        }
        foreach ($this->userList as $user) {
            $user->remove();
        }
    }

    public function testSearchReportId(): void {
        $search = new \Gazelle\Search\Report();
        $this->assertEquals(
            \Gazelle\Enum\SearchReportOrder::createdDesc,
            $search->order(),
            'search-report-default-order'
        );

        $search->setId($this->reportList['collage']->id());
        $this->assertEquals(1, $search->total(), 'search-report-id-total');
        $this->assertEquals(
            [$this->reportList['collage']->id()],
            $search->page(limit: 2, offset: 0),
            'search-report-page-id'
        );

        $search->setStatus(['Resolved']);
        $this->assertEquals(0, $search->total(), 'search-not-resolved-report-id');
    }

    public function testSearchReportList(): void {
        $search = new \Gazelle\Search\Report();
        $search->setStatus(['New']);

        $this->assertEquals(
            [
                $this->reportList['user']->id(),
                $this->reportList['request']->id(),
                $this->reportList['collage']->id(),
            ],
            $search->page(limit: 3, offset: 0),
            'search-report-page-list'
        );

        $this->reportList['request']->resolve($this->userList[0], new \Gazelle\Manager\Report(new \Gazelle\Manager\User()));
        $this->assertEquals(
            [
                $this->reportList['user']->id(),
                $this->reportList['collage']->id(),
            ],
            $search->page(limit: 2, offset: 0),
            'search-report-page-after-resolve-list'
        );

        $search->setTypeFilter(['collage']);
        $this->assertEquals(
            [$this->reportList['collage']->id()],
            $search->page(limit: 1, offset: 0),
            'search-report-page-id'
        );
    }
}
