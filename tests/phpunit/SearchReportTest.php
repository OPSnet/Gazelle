<?php

namespace Gazelle;

use PHPUnit\Framework\TestCase;
use GazelleUnitTest\Helper;

class SearchReportTest extends TestCase {
    protected array $reportList = [];
    protected array $userList   = [];
    protected Collage $collage;
    protected Request $request;

    public function setUp(): void {
        $this->userList = [
            Helper::makeUser('searchrep.' . randomString(10), 'searchrep', enable: true, clearInbox: true),
            Helper::makeUser('searchrep.' . randomString(10), 'searchrep', enable: true, clearInbox: true),
        ];

        $this->collage = new Manager\Collage()->create(
            user:        $this->userList[0],
            categoryId:  2,
            name:        'phpunit search report ' . randomString(20),
            description: 'phpunit search report description',
            tagList:     'disco funk metal',
        );

        $this->request = Helper::makeRequestMusic(
            user:            $this->userList[1],
            title:           'phpunit request report',
            encoding:        new Request\Encoding(list: ['Lossless']),
            format:          new Request\Format(list: ['FLAC']),
            media:           new Request\Media(list: ['WEB']),
            logCue:          new Request\LogCue(false, false, false, 0),
        );

        $manager = new Manager\Report(new Manager\User());
        $this->reportList['collage'] = $manager->create($this->userList[0], $this->collage->id, 'collage', 'phpunit search collage report');
        Helper::sleepTick();
        $this->reportList['request'] = $manager->create($this->userList[0], $this->request->id, 'request', 'phpunit search request report');
        Helper::sleepTick();
        $this->reportList['user'] = $manager->create($this->userList[0], $this->userList[1]->id, 'user', 'phpunit search user report');
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
        $search = new Search\Report();
        $this->assertSame(
            Enum\SearchReportOrder::createdDesc,
            $search->order(),
            'search-report-default-order'
        );

        $search->setId($this->reportList['collage']->id);
        $this->assertSame(1, $search->total(), 'search-report-id-total');
        $this->assertSame(
            [$this->reportList['collage']->id],
            $search->page(limit: 2, offset: 0),
            'search-report-page-id'
        );

        $search->setStatus(['Resolved']);
        $this->assertSame(0, $search->total(), 'search-not-resolved-report-id');
    }

    public function testSearchReportList(): void {
        $search = new Search\Report();
        $search->setStatus(['New']);

        $this->assertSame(
            [
                $this->reportList['user']->id,
                $this->reportList['request']->id,
                $this->reportList['collage']->id,
            ],
            $search->page(limit: 3, offset: 0),
            'search-report-page-list'
        );

        $this->reportList['request']->resolve($this->userList[0], new Manager\Report());
        $this->assertSame(
            [
                $this->reportList['user']->id,
                $this->reportList['collage']->id,
            ],
            $search->page(limit: 2, offset: 0),
            'search-report-page-after-resolve-list'
        );

        $search->setTypeFilter(['collage']);
        $this->assertSame(
            [$this->reportList['collage']->id],
            $search->page(limit: 1, offset: 0),
            'search-report-page-id'
        );
    }
}
