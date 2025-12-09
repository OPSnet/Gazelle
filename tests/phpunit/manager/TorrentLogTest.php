<?php

namespace Gazelle;

use GazelleUnitTest\Helper;
use PHPUnit\Framework\TestCase;
use OrpheusNET\Logchecker\Logchecker;

class TorrentLogTest extends TestCase {
    public function testFindById(): void {
        $user = null;
        $tgroup = null;
        try {
            $user = Helper::makeUser('torrentlog.' . randomString(6), 'torrentlog');
            $user->requestContext()->setViewer($user);
            $tgroup = Helper::makeTGroupMusic(
                $user,
                'phpunit torrentlog ' . randomString(6),
                [[ARTIST_MAIN], ['phpunit torrentlog artist ' . randomString(6)]],
                ['czech']
            );
            $torrent = Helper::makeTorrentMusic(
                tgroup: $tgroup,
                user:  $user,
                title: randomString(10),
            );
            $logfileSummary = new LogfileSummary([
                'error'    => [UPLOAD_ERR_OK],
                'name'     => ['valid_log_eac.log'],
                'tmp_name' => [__DIR__ . '/../fixture/valid_log_eac.log'],
            ]);

            $torrentLogManager = new Manager\TorrentLog();
            $checkerVersion = Logchecker::getLogcheckerVersion();
            $torrentLog = null;
            foreach ($logfileSummary->all() as $logfile) {
                $torrentLog = $torrentLogManager->create($torrent, $logfile, $checkerVersion);
            }
            $torrentLog2 = $torrentLogManager->findById($torrent, $torrentLog->id);
            $this->assertInstanceOf(TorrentLog::class, $torrentLog2);
            $this->assertEquals($torrentLog->id, $torrentLog2->id);
            $this->assertEquals($torrentLog->link(), $torrentLog2->link());
        } finally {
            if (isset($user)) {
                Helper::removeUser($user);
            }
        }
    }

    public function testFindByIdNull(): void {
        $manager = new Manager\TorrentLog();
        $this->assertNull($manager->findById(new Torrent(-1), 1));
    }
}
