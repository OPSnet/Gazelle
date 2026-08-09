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
        $this->assertSame(5, $paginator->total(), 'paginatior-1page-total');
        $this->assertSame($currentPage, $paginator->page(), 'paginatior-1page-page');
        $this->assertSame(1, $paginator->pages(), 'paginatior-1page-pages');
        $this->assertSame($perPage, $paginator->limit(), 'paginatior-1page-limit');
        $this->assertSame(0, $paginator->offset(), 'paginatior-1page-offset');
    }

    public function testPageCountFirst(): void {
        $perPage = 9;
        $currentPage = 1;
        $paginator = new Util\Paginator($perPage, $currentPage);

        $paginator->setTotal(55);
        $this->assertSame(55, $paginator->total(), 'paginatior-first-total');
        $this->assertSame($currentPage, $paginator->page(), 'paginatior-first-page');
        $this->assertSame(7, $paginator->pages(), 'paginatior-first-pages');
        $this->assertSame($perPage, $paginator->limit(), 'paginatior-first-limit');
        $this->assertSame(0, $paginator->offset(), 'paginatior-first-offset');
    }

    public function testPageCountLast(): void {
        $perPage = 9;
        $currentPage = 7;
        $paginator = new Util\Paginator($perPage, $currentPage);

        $paginator->setTotal(55);
        $this->assertSame(55, $paginator->total(), 'paginatior-last-total');
        $this->assertSame($currentPage, $paginator->page(), 'paginatior-last-page');
        $this->assertSame(7, $paginator->pages(), 'paginatior-last-pages');
        $this->assertSame($perPage, $paginator->limit(), 'paginatior-last-limit');
        $this->assertSame(54, $paginator->offset(), 'paginatior-last-offset');
    }

    public function testPageCountLastFull(): void {
        $perPage = 9;
        $currentPage = 6;
        $paginator = new Util\Paginator($perPage, $currentPage);

        $paginator->setTotal(54);
        $this->assertSame(54, $paginator->total(), 'paginatior-last-total');
        $this->assertSame($currentPage, $paginator->page(), 'paginatior-last-page');
        $this->assertSame(6, $paginator->pages(), 'paginatior-last-pages');
        $this->assertSame($perPage, $paginator->limit(), 'paginatior-last-limit');
        $this->assertSame(45, $paginator->offset(), 'paginatior-last-offset');
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
        $this->assertSame('stay=on', $paginator->params(), 'paginator-remove-params-noop');

        $_GET = ['stay' => 'on', 'remove' => 'this'];
        $this->assertSame('stay=on', $paginator->params(), 'paginator-remove-params-1');

        $_GET = ['stay' => '=<bad>', 'remove' => ''];
        $this->assertSame('stay=%3D%3Cbad%3E', $paginator->params(), 'paginator-linkbox-params-2');
    }

    public function testSetParam(): void {
        $perPage = 9;
        $currentPage = 1;

        $_GET = [];
        $paginator = new Util\Paginator($perPage, $currentPage);
        $paginator->setTotal(15)->setParam('new5', '1')->setParam('morenew', 'yes');
        $this->assertSame('new5=1&morenew=yes', $paginator->params(), 'paginator-setparam-1');

        $_GET = ['stay' => 'on'];
        $this->assertSame(
            'stay=on&new5=1&morenew=yes',
            $paginator->params([], false), 'paginator-setparam-2');
    }

    public function testParams(): void {
        $perPage = 9;
        $currentPage = 3;
        $paginator = new Util\Paginator($perPage, $currentPage);

        $_GET = ['page' => (string)$currentPage];
        $this->assertSame('', $paginator->params(), 'paginatior-params-without-page-1');
        $this->assertSame('page=3', $paginator->params([], true), 'paginatior-params-with-page-1');

        $_GET = ['action' => 'stuff', 'page' => $currentPage];
        $this->assertSame('action=stuff', $paginator->params(), 'paginatior-params-without-page-2');
        $this->assertSame('action=stuff&page=3', $paginator->params([], true), 'paginatior-params-with-page-2');
        $this->assertSame('', $paginator->params(['action']), 'paginatior-params-exclude-without');
        $this->assertSame('page=3', $paginator->params(['action'], true), 'paginatior-params-exclude-with');

        $_GET = ['action' => ['array', 'stuff'], 'page' => $currentPage];
        $this->assertSame('action%5B0%5D=array&action%5B1%5D=stuff', $paginator->params(), 'paginatior-params-array');
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
