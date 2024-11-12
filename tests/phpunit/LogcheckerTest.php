<?php

namespace Gazelle;

use PHPUnit\Framework\TestCase;
use OrpheusNET\Logchecker\Logchecker;
use OrpheusNET\Logchecker\Check\Checksum;

class LogcheckerTest extends TestCase {
    protected TGroup $tgroup;
    protected User   $user;

    public function tearDown(): void {
        if (isset($this->tgroup)) {
            \GazelleUnitTest\Helper::removeTGroup($this->tgroup, $this->user);
        }
        if (isset($this->user)) {
            $this->user->remove();
        }
    }

    public function testLogchecker(): void {
        $this->user = \GazelleUnitTest\Helper::makeUser('logchecker.' . randomString(6), 'logcheck');
        $this->tgroup = \GazelleUnitTest\Helper::makeTGroupMusic(
            $this->user,
            'phpunit logchecker ' . randomString(6),
            [[ARTIST_MAIN], ['phpunit logchecker artist ' . randomString(6)]],
            ['czech']
        );

        $this->assertMatchesRegularExpression(
            '/^\d+\.\d+\.\d+$/',
            Logchecker::getLogcheckerVersion(),
            'logchecker-version',
        );

        $logfile = new Logfile(
            __DIR__ . '/../fixture/valid_log_eac.log',
            'valid_log_eac.log'
        );

        $this->assertInstanceOf(Logfile::class, $logfile, 'logfile-instance');
        $this->assertTrue($logfile->checksum(), 'logfile-checksum');
        $this->assertEquals(Checksum::CHECKSUM_OK, $logfile->checksumState(), 'logfile-checksum-state');
        $this->assertEquals('1',  $logfile->checksumStatus(), 'logfile-checksum-status' );
        $this->assertCount(0, $logfile->details(), 'logfile-details');
        $this->assertEquals('', $logfile->detailsAsString(), 'logfile-details-string');
        $this->assertEquals('valid_log_eac.log', $logfile->filename(), 'logfile-filename');
        $this->assertEquals(100, $logfile->score(), 'logfile-score');
        $this->assertEquals('EAC', $logfile->ripper(), 'logfile-ripper');
        $this->assertEquals('1.3', $logfile->ripperVersion(), 'logfile-ripper-version');
        $this->assertEquals('en', $logfile->language(), 'logfile-language');

        $this->assertStringEndsWith(
            '/../fixture/valid_log_eac.log',
            $logfile->filepath(),
            'logfile-filepath'
        );
        $this->assertStringEndsWith(
            "<span class='good'>==== Log checksum 302419A0DBDD524D9ADA1079E2699F4C88F5C2FC46D2DA7CC3458BEE556953F5 ====</span>",
            $logfile->text(),
            'logfile-text'
        );
    }

    public function testLogcheckerSummary(): void {
        $this->user   = \GazelleUnitTest\Helper::makeUser('logchecker.' . randomString(6), 'logcheck-summary');
        $this->tgroup = \GazelleUnitTest\Helper::makeTGroupMusic(
            $this->user,
            'phpunit logchecker ' . randomString(6),
            [[ARTIST_MAIN], ['phpunit logchecker artist ' . randomString(6)]],
            ['czech']
        );
        $torrent = \GazelleUnitTest\Helper::makeTorrentMusic(
            tgroup: $this->tgroup,
            user:  $this->user,
            title: randomString(10),
        );
        $logfileSummary = new LogfileSummary([
            'error'    => [UPLOAD_ERR_OK],
            'name'     => ['valid_log_eac.log'],
            'tmp_name' => [__DIR__ . '/../fixture/valid_log_eac.log'],
        ]);
        $this->assertInstanceOf(LogfileSummary::class, $logfileSummary, 'logfilesummary-instance');

        $this->assertTrue($logfileSummary->checksum(), 'logfilesummary-checksum');
        $this->assertEquals('1', $logfileSummary->checksumStatus(), 'logfilesummary-checksum-status');
        $this->assertEquals(100, $logfileSummary->overallScore(), 'logfilesummary-overall-score');
        $this->assertCount(1, $logfileSummary->all(), 'logfilesummary-all');
        $this->assertEquals(1, $logfileSummary->total(), 'logfilesummary-total');

        $torrentLogManager = new Manager\TorrentLog(
            new File\RipLog(),
            new File\RipLogHTML()
        );
        $checkerVersion = Logchecker::getLogcheckerVersion();
        $torrentLog = null;
        foreach ($logfileSummary->all() as $logfile) {
            $torrentLog = $torrentLogManager->create($torrent, $logfile, $checkerVersion);
        }

        $this->assertInstanceOf(TorrentLog::class, $torrentLog, 'logfilesummary-torrentlog');
        $this->assertFalse($torrentLog->isAdjusted(), 'torrentlog-is-not-adjusted');
        $this->assertTrue($torrentLog->isChecksumOk(), 'torrentlog-is-checksum-ok');
        $this->assertEquals('valid_log_eac.log', $torrentLog->filename(), 'torrentlog-filename');
        $this->assertEquals(100, $torrentLog->score(), 'torrentlog-score');
        $this->assertEquals(100, $torrentLog->actualScore(), 'torrentlog-actual-score');
    }
}
