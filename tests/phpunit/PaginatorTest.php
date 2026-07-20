<?php

namespace Gazelle;

use PHPUnit\Framework\TestCase;
use Gazelle;

class PaginatorTest extends TestCase {
    public function testPageCountSingle(): void {
        $perPage = 9;
        $currentPage = 1;
        $paginator = new Util\Paginator($perPage, $currentPage);

        $paginator->setTotal(5);
        $this->assertEquals(5, $paginator->total(), 'paginatior-1page-total');
        $this->assertEquals($currentPage, $paginator->page(), 'paginatior-1page-page');
        $this->assertEquals(1, $paginator->pages(), 'paginatior-1page-pages');
        $this->assertEquals($perPage, $paginator->limit(), 'paginatior-1page-limit');
        $this->assertEquals(0, $paginator->offset(), 'paginatior-1page-offset');
    }

    public function testPageCountFirst(): void {
        $perPage = 9;
        $currentPage = 1;
        $paginator = new Util\Paginator($perPage, $currentPage);

        $paginator->setTotal(55);
        $this->assertEquals(55, $paginator->total(), 'paginatior-first-total');
        $this->assertEquals($currentPage, $paginator->page(), 'paginatior-first-page');
        $this->assertEquals(7, $paginator->pages(), 'paginatior-first-pages');
        $this->assertEquals($perPage, $paginator->limit(), 'paginatior-first-limit');
        $this->assertEquals(0, $paginator->offset(), 'paginatior-first-offset');
    }

    public function testPageCountLast(): void {
        $perPage = 9;
        $currentPage = 7;
        $paginator = new Util\Paginator($perPage, $currentPage);

        $paginator->setTotal(55);
        $this->assertEquals(55, $paginator->total(), 'paginatior-last-total');
        $this->assertEquals($currentPage, $paginator->page(), 'paginatior-last-page');
        $this->assertEquals(7, $paginator->pages(), 'paginatior-last-pages');
        $this->assertEquals($perPage, $paginator->limit(), 'paginatior-last-limit');
        $this->assertEquals(54, $paginator->offset(), 'paginatior-last-offset');
    }

    public function testPageCountLastFull(): void {
        $perPage = 9;
        $currentPage = 6;
        $paginator = new Util\Paginator($perPage, $currentPage);

        $paginator->setTotal(54);
        $this->assertEquals(54, $paginator->total(), 'paginatior-last-total');
        $this->assertEquals($currentPage, $paginator->page(), 'paginatior-last-page');
        $this->assertEquals(6, $paginator->pages(), 'paginatior-last-pages');
        $this->assertEquals($perPage, $paginator->limit(), 'paginatior-last-limit');
        $this->assertEquals(45, $paginator->offset(), 'paginatior-last-offset');
    }

    public function testLinkboxAnchor(): void {
        $perPage = 9;
        $currentPage = 1;
        $paginator = new Util\Paginator($perPage, $currentPage);

        $paginator->setTotal(15)->setAnchor('phpunit');
        $_SERVER['DOCUMENT_URI'] = '/paginator_test.php';
        $_GET = ['page' => (string)$currentPage];

        $linkbox = $paginator->linkbox();
        $this->assertMatchesRegularExpression(
            '|<a href="/paginator_test\.php\?page=2#phpunit"><b>10-15|',
            $linkbox, 'paginator-linkbox-anchor');
        $this->assertMatchesRegularExpression('|<b>1-9</b>|', $linkbox, 'paginator-linkbox-anchor-page');
    }

    public function testRemoveParam(): void {
        $perPage = 9;
        $currentPage = 1;
        $_SERVER['DOCUMENT_URI'] = '/paginator_test.php';

        $_GET = ['stay' => 'on'];
        $paginator = new Util\Paginator($perPage, $currentPage);
        $paginator->setTotal(15)->removeParam('remove');
        $this->assertEquals('stay=on', $paginator->params(), 'paginator-remove-params-noop');

        $_GET = ['stay' => 'on', 'remove' => 'this'];
        $this->assertEquals('stay=on', $paginator->params(), 'paginator-remove-params-1');

        $_GET = ['stay' => '=<bad>', 'remove' => ''];
        $this->assertEquals('stay=%3D%3Cbad%3E', $paginator->params(), 'paginator-linkbox-params-2');
    }

    public function testSetParam(): void {
        $perPage = 9;
        $currentPage = 1;

        $_GET = [];
        $paginator = new Util\Paginator($perPage, $currentPage);
        $paginator->setTotal(15)->setParam('new5', '1')->setParam('morenew', 'yes');
        $this->assertEquals('new5=1&morenew=yes', $paginator->params(), 'paginator-setparam-1');

        $_GET = ['stay' => 'on'];
        $this->assertEquals(
            'stay=on&new5=1&morenew=yes',
            $paginator->params([], false), 'paginator-setparam-2');
    }

    public function testParams(): void {
        $perPage = 9;
        $currentPage = 3;
        $paginator = new Util\Paginator($perPage, $currentPage);

        $_GET = ['page' => (string)$currentPage];
        $this->assertEquals('', $paginator->params(), 'paginatior-params-without-page-1');
        $this->assertEquals('page=3', $paginator->params([], true), 'paginatior-params-with-page-1');

        $_GET = ['action' => 'stuff', 'page' => $currentPage];
        $this->assertEquals('action=stuff', $paginator->params(), 'paginatior-params-without-page-2');
        $this->assertEquals('action=stuff&page=3', $paginator->params([], true), 'paginatior-params-with-page-2');
        $this->assertEquals('', $paginator->params(['action']), 'paginatior-params-exclude-without');
        $this->assertEquals('page=3', $paginator->params(['action'], true), 'paginatior-params-exclude-with');

        $_GET = ['action' => ['array', 'stuff'], 'page' => $currentPage];
        $this->assertEquals('action%5B0%5D=array&action%5B1%5D=stuff', $paginator->params(), 'paginatior-params-array');
    }

    public function testLinkboxFullPage(): void {
        $perPage = 9;
        $currentPage = 2;

        $paginator = new Util\Paginator($perPage, $currentPage);
        $paginator->setTotal(18);
        $_SERVER['DOCUMENT_URI'] = '/paginator_test.php';
        $_GET = ['stay' => 'on'];

        $linkbox = $paginator->linkbox();
        $this->assertMatchesRegularExpression('| <b>10-18</b>|', $linkbox, 'paginator-linkbox-full-1');
        $this->assertMatchesRegularExpression(
            '|&amp;page=1|', $linkbox, 'paginator-linkbox-full-2');
        $this->assertDoesNotMatchRegularExpression(
            '|&amp;page=2|', $linkbox, 'paginator-linkbox-full-3');

        $paginator = new Util\Paginator($perPage, 1);
        $paginator->setTotal(18);
        $linkbox = $paginator->linkbox();
        $this->assertMatchesRegularExpression('|<b>1-9</b> |', $linkbox, 'paginator-linkbox-full-4');
        $this->assertDoesNotMatchRegularExpression(
            '|&amp;page=3|', $linkbox, 'paginator-linkbox-full-5');
    }
}
