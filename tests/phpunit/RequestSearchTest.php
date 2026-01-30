<?php

namespace Gazelle;

use PHPUnit\Framework\TestCase;
use GazelleUnitTest\Helper;

class RequestSearchTest extends TestCase {
    use Pg;

    protected array $requestList;
    protected array $userList;
    protected array $tagList;
    protected TGroup $tgroup;

    public function setUp(): void {
        $this->userList = [
            Helper::makeUser('req.' . randomString(10), 'request-search'),
        ];
    }

    public function tearDown(): void {
        if (isset($this->requestList)) {
            foreach ($this->requestList as $r) {
                $r->remove();
            }
        }
        if (isset($this->tagList)) {
            foreach ($this->tagList as $t) {
                $t->remove();
            }
        }
        if (isset($this->tgroup)) {
            Helper::removeTGroup($this->tgroup, $this->userList[0]);
        }
        foreach ($this->userList as $user) {
            $user->requestContext()->unsetViewer();
            $user->remove();
        }
    }

    public function testRequestSearchBasic(): void {
        $year = 1999;
        $tagManager = new Manager\Tag();
        $tagList = [
            $tagManager->create('ut-' . randomString(8), $this->userList[0]),
            $tagManager->create('ut-' . randomString(8), $this->userList[0]),
            $tagManager->create('ut-' . randomString(8), $this->userList[0]),
        ];
        $title = [
            'title ' . randomString(16),
            'title ' . randomString(16),
        ];
        $search = [
            'category' => new Search\Request()->setCategory([CATEGORY_MUSIC - 1]),
            'year'     => new Search\Request()->setYear($year),
        ];
        $initial = [];
        foreach ($search as $name => $s) {
            $initial[$name] = $s->total();
        }

        $creator = new Search\Request()->setCreator($this->userList[0]);
        $this->assertEquals(0, $creator->total(), 'reqs-basic-no-creator');

        $this->requestList[] = Helper::makeRequestMusic(
            user:        $this->userList[0],
            title:       $title[0],
            releaseType: 5,
            tagList:     [$tagList[0]->id, $tagList[1]->id],
            year:        $year,
        );

        $this->assertEquals(1, $creator->total(), 'reqs-creator-total');
        $this->assertEquals(
            $initial['category'] + 1,
            $search['category']->total(),
            'reqs-category-total',
        );
        $this->assertEquals(
            $initial['year'] + 1,
            $search['year']->total(),
            'reqs-year-total',
        );

        $result = $creator->page(2, 0);
        $this->assertCount(1, $result, 'reqs-page-count');
        $this->assertEquals(
            $this->requestList[0]->id,
            $result[0]->id,
            'reqs-page-entry',
        );
    }

    public function testRequestSearchBookmark(): void {
        $title = 'title ' . randomString(16);
        $user  = $this->userList[0];
        $this->requestList[] = Helper::makeRequestMusic(
            user:  $user,
            title: $title,
        );
        $this->assertTrue(
            new User\Bookmark($user)->create($this->requestList[0]),
            'reqs-bookmark-create',
        );

        $bookmarker = new Search\Request()->setBookmarker($user);
        $this->assertEquals(
            ['bookmarker'],
            array_keys($bookmarker->info()),
            'reqs-info'
        );
        $this->assertTrue($bookmarker->isBookmarkView(), 'reqs-is-bookmarker');
        $this->assertEquals(1, $bookmarker->total(), 'reqs-bookmarker-total');
        $result = $bookmarker->page(1, 0);
        $this->assertCount(1, $result, 'reqs-bookmark-page-count');
        $this->assertEquals(
            $this->requestList[0]->id,
            $result[0]->id,
            'reqs-bookmark-page-entry',
        );
    }

    public function testRequestSearchFiller(): void {
        $title   = 'title ' . randomString(16);
        $filler  = new Search\Request();
        $initial = $filler->showFilled()->total(); // all the current filled requests
        $this->assertTrue($filler->isShowFilled(), 'reqs-filler-shows-filled');
        $this->requestList[] = Helper::makeRequestMusic(
            user:     $this->userList[0],
            title:    $title,
            encoding: new Request\Encoding(list: ['FLAC']),
            format:   new Request\Format(list: ['Lossless']),
            media:    new Request\Media(list:  ['Cassette']),
        );
        $user = Helper::makeUser('req.' . randomString(10), 'request-search-filler');
        $user->requestContext()->setViewer($user);
        $this->userList[] = $user;
        $this->tgroup = Helper::makeTGroupMusic(
            $user,
            'phpunit reqfill ' . randomString(10),
            [[ARTIST_MAIN], ['phpunit reqfill ' . randomString(6)]],
            [
                new Manager\Tag()->create('ut-' . randomString(8), $this->userList[0])->id,
            ],
        );
        $torrent = Helper::makeTorrentMusic(
            $this->tgroup, $user, 'Cassette', 'FLAC', 'Lossless',
        );
        $this->assertEquals(1, $this->requestList[0]->fill($user, $torrent), 'request-search-fill');
        new Manager\Request()->relay(); // FIXME: remove when Pg migration is complete

        $filler->flush();
        $filler->setFiller($user)->showFilled();
        $this->assertEquals(1, $filler->total(), 'reqs-filler-total');
        $result = $filler->page(1, 0);
        $this->assertCount(1, $result, 'reqs-filler-page-count');
        $this->assertEquals($this->requestList[0]->id, $result[0]->id, 'reqs-filler-page-entry');
        $this->assertEquals(
            $initial + 1,
            $filler->flush()->showFilled()->total(),
            'reqs-filler-filled',
        );
    }

    protected function setupValueSearch(string $title): void {
        $this->requestList = [
            Helper::makeRequestMusic(
                user:        $this->userList[0],
                title:       "$title 1",
                encoding:    new Request\Encoding(list: ['24bit Lossless']),
                format:      new Request\Format(list: ['FLAC']),
                media:       new Request\Media(list: ['Vinyl']),
                releaseType: 6,
            ),
            Helper::makeRequestMusic(
                user:        $this->userList[0],
                title:       "$title 2",
                encoding:    new Request\Encoding(list: ['320']),
                format:      new Request\Format(list: ['MP3']),
                media:       new Request\Media(list: ['BD']),
                releaseType: 7,
            ),
            Helper::makeRequestMusic(
                user:        $this->userList[0],
                title:       "$title 3",
                encoding:    new Request\Encoding(list: []), // Any
                format:      new Request\Format(list: []),
                media:       new Request\Media(list: []),
                releaseType: 8,
            ),
        ];
    }

    public function testRequestSearchValueEncode(): void {
        // not present in the subsequent requests
        $nomatchStrict = new Search\Request()->setEncoding(['Lossless'], strict: true);
        $nomatchLoose  = new Search\Request()->setEncoding(['Lossless'], strict: false);
        // is present in the subsequent requests
        $matchStrict   = new Search\Request()->setEncoding(['24bit Lossless'], strict: true);
        $matchLoose    = new Search\Request()->setEncoding(['24bit Lossless'], strict: false);

        $nomatchStrictTotal = $nomatchStrict->total();
        $nomatchLooseTotal  = $nomatchLoose->total();
        $matchStrictTotal   = $matchStrict->total();
        $matchLooseTotal    = $matchLoose->total();

        $title = 'title ' . randomString(16);
        $this->setupValueSearch($title);

        $this->assertEquals($nomatchStrictTotal,    $nomatchStrict->total(), 'reqs-enc-nomatch-strict');
        $this->assertEquals($nomatchLooseTotal + 1, $nomatchLoose->total(), 'reqs-enc-nomatch-loose');
        $this->assertEquals($matchStrictTotal  + 1, $matchStrict->total(), 'reqs-enc-match-strict');
        $this->assertEquals($matchLooseTotal   + 2, $matchLoose->total(), 'reqs-enc-match-loose');

        $this->assertEquals(
            ['list' => ['24bit Lossless'], 'strict' => true],
            $matchStrict->encodingList(),
            'reqs-encoding-list',
        );
    }

    public function testRequestSearchValueFormat(): void {
        $nomatchStrict      = new Search\Request()->setFormat(['AAC'], strict: true);
        $nomatchLoose       = new Search\Request()->setFormat(['AAC'], strict: false);
        $matchStrict        = new Search\Request()->setFormat(['FLAC'], strict: true);
        $matchLoose         = new Search\Request()->setFormat(['FLAC'], strict: false);
        $nomatchStrictTotal = $nomatchStrict->total();
        $nomatchLooseTotal  = $nomatchLoose->total();
        $matchStrictTotal   = $matchStrict->total();
        $matchLooseTotal    = $matchLoose->total();
        $title = 'title ' . randomString(16);
        $this->setupValueSearch($title);

        $this->assertEquals($nomatchStrictTotal,    $nomatchStrict->total(), 'reqs-format-nomatch-strict');
        $this->assertEquals($nomatchLooseTotal + 1, $nomatchLoose->total(), 'reqs-format-nomatch-loose');
        $this->assertEquals($matchStrictTotal  + 1, $matchStrict->total(), 'reqs-format-match-strict');
        $this->assertEquals($matchLooseTotal   + 2, $matchLoose->total(), 'reqs-format-match-loose');

        $this->assertEquals(
            ['list' => ['FLAC'], 'strict' => true],
            $matchStrict->formatList(),
            'reqs-format-list',
        );
    }

    public function testRequestSearchValueMedia(): void {
        $nomatchStrict      = new Search\Request()->setMedia(['Cassette'], strict: true);
        $nomatchLoose       = new Search\Request()->setMedia(['Cassette'], strict: false);
        $matchStrict        = new Search\Request()->setMedia(['Vinyl'], strict: true);
        $matchLoose         = new Search\Request()->setMedia(['Vinyl'], strict: false);
        $nomatchStrictTotal = $nomatchStrict->total();
        $nomatchLooseTotal  = $nomatchLoose->total();
        $matchStrictTotal   = $matchStrict->total();
        $matchLooseTotal    = $matchLoose->total();
        $title = 'title ' . randomString(16);
        $this->setupValueSearch($title);

        $this->assertEquals($nomatchStrictTotal,    $nomatchStrict->total(), 'reqs-media-nomatch-strict');
        $this->assertEquals($nomatchLooseTotal + 1, $nomatchLoose->total(), 'reqs-media-nomatch-loose');
        $this->assertEquals($matchStrictTotal  + 1, $matchStrict->total(), 'reqs-media-match-strict');
        $this->assertEquals($matchLooseTotal   + 2, $matchLoose->total(), 'reqs-media-match-loose');

        $this->assertEquals(
            ['list' => ['Vinyl'], 'strict' => true],
            $matchStrict->mediaList(),
            'reqs-media-list',
        );
    }

    public function testRequestSearchReleaseType(): void {
        $nomatch      = new Search\Request()->setReleaseType([16, 17]);
        $match        = new Search\Request()->setReleaseType([6, 7]);
        $nomatchTotal = $nomatch->total();
        $matchTotal   = $match->total();
        $title = 'title ' . randomString(16);
        $this->setupValueSearch($title);

        $this->assertEquals($nomatchTotal,    $nomatch->total(), 'reqs-rtype-nomatch-strict');
        $this->assertEquals($matchTotal  + 2, $match->total(), 'reqs-rtype-match-strict');

        $this->assertEquals(
            [6, 7],
            $match->releaseTypeList(),
            'reqs-rtype-list',
        );
    }

    public function testRequestSearchTag(): void {
        $title = 'title ' . randomString(16);
        $this->setupValueSearch($title);
        $tagManager = new Manager\Tag();
        $tagList = [
            $tagManager->create('ut-' . randomString(8), $this->userList[0]),
            $tagManager->create('ut-' . randomString(8), $this->userList[0]),
            $tagManager->create('ut-' . randomString(8), $this->userList[0]),
        ];
        $tagManager->replaceTagList(
            $this->requestList[0],
            [$tagList[0]->name(), $tagList[1]->name()],
            $this->userList[0],
        );
        $tagManager->replaceTagList(
            $this->requestList[1],
            [$tagList[0]->name(), $tagList[2]->name()],
            $this->userList[0],
        );
        $tagManager->replaceTagList(
            $this->requestList[2],
            [$tagList[1]->name(), $tagList[2]->name()],
            $this->userList[0],
        );
        // requests are saved with second granularity, so spread them out
        $this->requestList[1]->setField('created', date('Y-m-d H:i:s', time() + 2))->modify();
        $this->requestList[2]->setField('created', date('Y-m-d H:i:s', time() + 4))->modify();

        $search = new Search\Request()->setTag("{$tagList[0]->name()}", Enum\SearchTag::all);
        $this->assertEquals(2, $search->total(), 'reqs-tag-1-all-total');
        $this->assertEquals(
            [$this->requestList[1]->id, $this->requestList[0]->id],
            array_map(fn ($r) => $r->id, $search->page(2, 0)),
            'reqs-tag-1-all-page'
        );
        $this->assertEquals(
            [
                'list' => "{$tagList[0]->name()}",
                'mode' => Enum\SearchTag::all,
            ],
            $search->tagList(),
            'reqs-tag-list',
        );

        $search->flush()->setTag("{$tagList[0]->name()},{$tagList[1]->name()}", Enum\SearchTag::all);
        $this->assertEquals(1, $search->total(), 'reqs-tag-2-all');
        $this->assertEquals(
            [$this->requestList[0]->id],
            array_map(fn ($r) => $r->id, $search->page(1, 0)),
            'reqs-tag-2-all-page'
        );

        $search->flush()->setTag("{$tagList[0]->name()},{$tagList[1]->name()}", Enum\SearchTag::any);
        $this->assertEquals(3, $search->total(), 'reqs-tag-2-any');
        $this->assertEqualsCanonicalizing(
            [$this->requestList[2]->id, $this->requestList[1]->id, $this->requestList[0]->id],
            array_map(fn ($r) => $r->id, $search->page(3, 0)),
            'reqs-tag-2-any-page'
        );

        $search->flush()->setTag("{$tagList[1]->name()},!{$tagList[2]->name()}", Enum\SearchTag::any);
        $this->assertEquals(1, $search->total(), 'reqs-tag-1-not-2-total');
        $this->assertEquals(
            [$this->requestList[0]->id],
            array_map(fn ($r) => $r->id, $search->page(3, 0)),
            'reqs-tag-1-not-2-page'
        );

        // do full text search while we are here
        $search->flush()->setSearch($title);
        $this->assertEquals(3, $search->total(), 'reqs-match-search-total');
        $this->assertEqualsCanonicalizing(
            [$this->requestList[2]->id, $this->requestList[1]->id, $this->requestList[0]->id],
            array_map(fn ($r) => $r->id, $search->page(3, 0)),
            'reqs-match-search-page'
        );
    }

    public function testRequestSearchVoter(): void {
        $title = 'title ' . randomString(16);
        $this->requestList[] = Helper::makeRequestMusic(
            user:  $this->userList[0],
            title: $title,
        );
        $user = Helper::makeUser('req.' . randomString(10), 'request-search-voter');
        $this->userList[] = $user;
        $this->requestList[0]->vote($user, 100000);

        $voter = new Search\Request()->setVoter($user);
        $this->assertEquals(1, $voter->total(), 'reqs-voter-total');
        $result = $voter->page(1, 0);
        $this->assertCount(1, $result, 'reqs-voter-page-count');
        $this->assertEquals($this->requestList[0]->id, $result[0]->id, 'reqs-voter-page-entry');
    }
}
