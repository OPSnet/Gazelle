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
        $_SERVER['REQUEST_URI'] = SITE_URL . '/paginator_test.php';

        $linkbox = $paginator->linkbox();
        $this->assertMatchesRegularExpression(
            '|<a href="[^"]+/paginator_test\.php\?page=2#phpunit"><strong>10-15|',
            $linkbox, 'paginator-linkbox-anchor');
        $this->assertMatchesRegularExpression('|<strong>1-9</strong>|', $linkbox, 'paginator-linkbox-anchor-page');
    }

    public function testLinkboxRemoveParam(): void {
        $perPage = 9;
        $currentPage = 1;

        $paginator = new Util\Paginator($perPage, $currentPage);
        $paginator->setTotal(15)->removeParam('remove');
        $_SERVER['REQUEST_URI'] = SITE_URL . '/paginator_test.php?remove=yes&stay=on';

        $linkbox = $paginator->linkbox();
        $this->assertMatchesRegularExpression(
            '|<a href="[^"]+/paginator_test\.php\?stay=on&amp;page=2"><strong>10-15|',
            $linkbox, 'paginator-linkbox-remparam-1');

        $paginator = new Util\Paginator($perPage, $currentPage);
        $paginator->setTotal(15)->removeParam('remove');
        $_SERVER['REQUEST_URI'] = SITE_URL . '/paginator_test.php?stay=on&remove=yes';
        $this->assertEquals($linkbox, $paginator->linkbox(), 'paginator-linkbox-remparam-2');

        $paginator = new Util\Paginator($perPage, $currentPage);
        $paginator->setTotal(15)->removeParam('remove');
        $_SERVER['REQUEST_URI'] = SITE_URL . '/paginator_test.php?remove=yes';
        $this->assertMatchesRegularExpression(
            '|<a href="[^"]+/paginator_test\.php\?page=2"><strong>10-15|',
            $paginator->linkbox(), 'paginator-linkbox-remparam-bare');
    }

    public function testLinkboxSetParam(): void {
        $perPage = 9;
        $currentPage = 1;

        $paginator = new Util\Paginator($perPage, $currentPage);
        $paginator->setTotal(15)->setParam('new5', '1')->setParam('morenew', 'yes');
        $_SERVER['REQUEST_URI'] = SITE_URL . '/paginator_test.php?stay=on';

        $this->assertMatchesRegularExpression(
            '|<a href="[^"]+/paginator_test\.php\?stay=on&amp;page=2&amp;new5=1&amp;morenew=yes"><strong>10-15|',
            $paginator->linkbox(), 'paginator-linkbox-setparam-1');

        $paginator = new Util\Paginator($perPage, $currentPage);
        $paginator->setTotal(15)->setParam('new5', '1')->setParam('morenew', 'yes');
        $_SERVER['REQUEST_URI'] = SITE_URL . '/paginator_test.php';
        $this->assertMatchesRegularExpression(
            '|<a href="[^"]+/paginator_test\.php\?page=2&amp;new5=1&amp;morenew=yes"><strong>10-15|',
            $paginator->linkbox(), 'paginator-linkbox-setparam-2');
    }

    public function testLinkboxFullPage(): void {
        $perPage = 9;
        $currentPage = 2;

        $paginator = new Util\Paginator($perPage, $currentPage);
        $paginator->setTotal(18);
        $_SERVER['REQUEST_URI'] = SITE_URL . '/paginator_test.php';

        $linkbox = $paginator->linkbox();
        $this->assertMatchesRegularExpression('| <strong>10-18</strong>|', $linkbox, 'paginator-linkbox-full-1');
        $this->assertDoesNotMatchRegularExpression(
            '|<a href="[^"]+/paginator_test\.php\?page=3|', $linkbox, 'paginator-linkbox-full-2');

        $paginator = new Util\Paginator($perPage, 1);
        $paginator->setTotal(18);
        $linkbox = $paginator->linkbox();
        $this->assertMatchesRegularExpression('|<strong>1-9</strong> |', $linkbox, 'paginator-linkbox-full-3');
        $this->assertDoesNotMatchRegularExpression(
            '|<a href="[^"]+/paginator_test\.php\?page=3|', $linkbox, 'paginator-linkbox-full-4');
    }
}
