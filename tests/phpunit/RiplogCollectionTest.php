<?php

namespace Gazelle;

use GazelleUnitTest\Helper;
use OrpheusNET\Logchecker\Logchecker;
use PHPUnit\Framework\TestCase;

class RiplogCollectionTest extends TestCase {
    protected Torrent $torrent;
    protected User    $user;

    public function setUp(): void {
        $this->user = Helper::makeUser('riplog.' . randomString(10), 'rent', clearInbox: true);
        $this->torrent = Helper::makeTorrentMusic(
            tgroup: Helper::makeTGroupMusic(
                name:       'phpunit torrent ' . randomString(6),
                artistName: [[ARTIST_MAIN], ['phpunit torrent ' . randomString(12)]],
                tagName:    ['jazz'],
                user:       $this->user,
            ),
            user:  $this->user,
            title: randomString(10),
        );
    }

    public function tearDown(): void {
        Helper::removeUser($this->user);
    }

    public function testRiplogList(): void {
        $manager = new Manager\Riplog();
        $riplog  = $manager->create(
            $this->torrent,
            __DIR__ . '/../fixture/valid_log_eac.log',
            'valid_log_eac.log',
        );
        $collection = $manager->collection($this->torrent);
        $this->assertEquals(
            $riplog->id,
            $collection->riplogList()[0]->id,
            'riplog-collection-list',
        );

        $this->assertTrue($collection->checksum(), 'logfilesummary-checksum');
        $this->assertEquals('1', $collection->checksumStatus(), 'logfilesummary-checksum-status');
        $this->assertEquals(100, $collection->overallScore(), 'logfilesummary-overall-score');
        $this->assertEquals(1, $collection->total(), 'logfilesummary-total');
    }

    public function testDuplicateFilesIgnored(): void {
        $manager = new Manager\Riplog();
        $manager->bulkCreate(
            $this->torrent,
            [
                'error' => [
                    UPLOAD_ERR_OK,
                    UPLOAD_ERR_OK,
                ],
                'tmp_name' => [
                    __DIR__ . '/../fixture/valid_log_eac.log',
                    __DIR__ . '/../fixture/valid_log_eac.log',
                ],
                'name' => [
                    'valid_log_eac.log',
                    'valid_log_eac.log',
                ],
            ],
        );
        $collection = $manager->collection($this->torrent);

        $this->assertTrue($collection->checksum(), 'riplog-collection-checksum');
        $this->assertEquals('1', $collection->checksumStatus(), 'riplog-collection-checksum-status');
        $this->assertEquals(100, $collection->overallScore(), 'riplog-collection-overall-score');
        $this->assertEquals(1, $collection->total(), 'riplog-collection-total');
    }
}
