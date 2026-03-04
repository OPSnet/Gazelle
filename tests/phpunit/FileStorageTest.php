<?php

namespace Gazelle;

use PHPUnit\Framework\TestCase;

class FileStorageTest extends TestCase {
    public function testRipLogPath(): void {
        $this->assertEquals(
            STORAGE_PATH_RIPLOG  . '/71/00/17_34.log',
            new File\RipLog(17, 34)->path(),
            'file-riplog-17-14',
        );
        $this->assertEquals(
            STORAGE_PATH_RIPLOG  . '/73/16/16137_707.log',
            new File\RipLog(16137, 707)->path(),
            'file-riplog-16137-707'
        );
        $this->assertEquals(
            STORAGE_PATH_RIPLOG  . '/73/16/16137_708.log',
            new File\RipLog(16137, 708)->path(),
            'file-riplog-16137-708'
        );
    }

    public function testRipLogHTMLPath(): void {
        $this->assertEquals(
            STORAGE_PATH_RIPLOGHTML  . '/72/00/27_44.html',
            new File\RipLogHTML(27, 44)->path(),
            'file-html-27-44'
        );
        $this->assertEquals(
            STORAGE_PATH_RIPLOGHTML  . '/73/16/26137_807.html',
            new File\RipLogHTML(26137, 807)->path(),
            'file-html-26137-807'
        );
        $this->assertEquals(
            STORAGE_PATH_RIPLOGHTML  . '/73/16/26137_808.html',
            new File\RipLogHTML(26137, 808)->path(),
            'file-html-26137-808'
        );
    }

    public function testTorrentPath(): void {
        $this->assertEquals(
            STORAGE_PATH_TORRENT . '/10/00/1.torrent',
            new File\Torrent(1)->path(),
            'file-torrent-00001'
        );
        $this->assertEquals(
            STORAGE_PATH_TORRENT . '/01/00/10.torrent',
            new File\Torrent(10)->path(),
            'file-torrent-00010'
        );
        $this->assertEquals(
            STORAGE_PATH_TORRENT . '/00/10/100.torrent',
            new File\Torrent(100)->path(),
            'file-torrent-00100'
        );
        $this->assertEquals(
            STORAGE_PATH_TORRENT . '/00/01/1000.torrent',
            new File\Torrent(1000)->path(),
            'file-torrent-01000'
        );
        $this->assertEquals(
            STORAGE_PATH_TORRENT . '/00/00/10000.torrent',
            new File\Torrent(10000)->path(),
            'file-torrent-10000'
        );
        $this->assertEquals(
            STORAGE_PATH_TORRENT . '/10/00/10001.torrent',
            new File\Torrent(10001)->path(),
            'file-torrent-10001'
        );
        $this->assertEquals(
            STORAGE_PATH_TORRENT . '/20/00/10002.torrent',
            new File\Torrent(10002)->path(),
            'file-torrent-10002'
        );

        $this->assertEquals(
            STORAGE_PATH_TORRENT . '/43/21/1234.torrent',
            new File\Torrent(1234)->path(),
            'file-torrent-1234'
        );
        $this->assertEquals(
            STORAGE_PATH_TORRENT . '/53/21/1235.torrent',
            new File\Torrent(1235)->path(),
            'file-torrent-1235'
        );
        $this->assertEquals(
            STORAGE_PATH_TORRENT . '/43/21/81234.torrent',
            new File\Torrent(81234)->path(),
            'file-torrent-81234'
        );
    }

    public function testFileRipLogHTML(): void {
        $file = new File\RipLogHTML(2651337, 306);
        $this->assertFalse($file->exists(),     'file-h-not-exists');
        $this->assertFalse($file->get(),        'file-h-not-get');
        $this->assertEquals(0, $file->remove(), 'file-h-not-remove');

        $text = "<h1>phpunit test " . randomString() . "</h1>";
        $this->assertTrue($file->put($text), 'file-h-put-ok');

        $this->assertTrue($file->exists(),         'file-h-exists-ok');
        $this->assertEquals($text, $file->get(),   'file-h-get-ok');
        $this->assertEquals(1, $file->remove(),    'file-h-remove-ok');
        $this->assertFalse($file->flush()->get(),  'file-h-get-after-remove');
        $this->assertEquals('', $file->link(),     'file-h-link');
        $this->assertEquals('', $file->location(), 'file-h-location');
    }

    public function testFileRipLogBinary(): void {
        /**
         * = File\RipLog cannot easily be unit-tested, as PHP goes to great
         * lengths to ensure there is no funny business happening during file
         * uploads and there is no easy way to mock it.
         */
        $file = new File\RipLog(0, 0);
        $this->assertFalse($file->exists(), 'file-r-exists-nok');
        $this->assertFalse($file->get(),    'file-r-get-nok');

        $json = new Json\RipLog(0, 0);
        $response = json_decode($json->response(), true);
        $this->assertEquals('failure', $response['status'], 'json-riplog-failure');

        $this->assertFalse($file->flush()->get(),  'file-riplog-get-after-remove');
        $this->assertEquals('', $file->link(),     'file-riplog-link');
        $this->assertEquals('', $file->location(), 'file-riplog-location');
    }

    public function testFileTorrent(): void {
        $file = new File\Torrent(906622);
        $text  = "This is a phpunit torrent file";
        $this->assertTrue($file->put($text),     'file-t-put-ok');
        $this->assertEquals($text, $file->get(), 'file-t-get-ok');
        $this->assertEquals(1, $file->remove(),  'file-t-put-ok');

        $this->assertFalse($file->flush()->get(),  'file-t-get-after-remove');
        $this->assertEquals('', $file->link(),     'file-t-link');
        $this->assertEquals('', $file->location(), 'file-t-location');
    }

    public function testFileHashFailure(): void {
        $file = new File\Torrent(906623);
        $this->expectException(\Exception::class);
        $file->hash();
    }
}
