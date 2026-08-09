<?php

namespace Gazelle;

use GazelleUnitTest\Helper;
use PHPUnit\Framework\TestCase;
use OrpheusNET\Logchecker\Check\Checksum;
use OrpheusNET\Logchecker\Logchecker;

class RiplogTest extends TestCase {
    protected User $user;

    public function tearDown(): void {
        if (isset($this->user)) {
            Helper::removeUser($this->user);
        }
    }

    public function testFindById(): void {
        $this->user = Helper::makeUser('riplog.' . randomString(6), 'riplog');
        $torrent    = Helper::makeTorrentMusic(
            tgroup: Helper::makeTGroupMusic(
                $this->user,
                'phpunit riplog ' . randomString(6),
                [[ARTIST_MAIN], ['phpunit riplog artist ' . randomString(6)]],
                ['czech']
            ),
            user: $this->user,
        );
        $riplogManager = new Manager\Riplog();
        $riplog = $riplogManager->create(
            $torrent,
            __DIR__ . '/../fixture/valid_log_eac.log',
            'valid_log_eac.log',
        );

        $this->assertSame($torrent->id, $riplog->torrent->id, 'torrent-riplog-create');
        $this->assertTrue($riplog->isChecksumOk(), 'torrent-riplog-checksum');

        $riplog2 = $riplogManager->findById($torrent, $riplog->id);
        $this->assertInstanceOf(Riplog::class, $riplog2, 'riplog-find-by-logid');
        $this->assertSame($riplog->id, $riplog2->id, 'riplog-id');
        $this->assertSame($riplog->link(), $riplog2->link(), 'riplog-link');

        $this->assertSame(
            [$riplog->id],
            $riplogManager->fetchIdList($torrent),
            'torrent-riplog-id-list',
        );
    }

    public function testFindByIdNull(): void {
        $manager = new Manager\Riplog();
        $this->assertNull($manager->findById(new Torrent(-1), 1));
    }

    public function testRiplogResult(): void {
        $this->user = Helper::makeUser('riplog.' . randomString(6), 'riplog');
        $torrent    = Helper::makeTorrentMusic(
            tgroup: Helper::makeTGroupMusic(
                $this->user,
                'phpunit riplog ' . randomString(6),
                [[ARTIST_MAIN], ['phpunit riplog artist ' . randomString(6)]],
                ['czech']
            ),
            user:   $this->user,
            hasLog: true,
        );
        $riplogManager = new Manager\Riplog();
        $riplog = $riplogManager->create(
            $torrent,
            __DIR__ . '/../fixture/valid_log_eac.log',
            'valid_log_eac.log',
        );

        $torrent->setField('HasLogDB', '1')->modify();
        $this->assertSame(
            [
                $torrent->id => [
                    'id'   => $torrent->id,
                    'link' => $torrent->fullLink(),
                ],
            ],
            new Manager\Torrent()->logFileList($torrent->uploader()->id),
            'torman-user-riplog-list',
        );

        $this->assertTrue($riplog->isChecksumOk(), 'riplog-checksum');
        $this->assertSame(Enum\RiplogChecksum::ok, $riplog->checksum(), 'riplog-checksum-state');
        $this->assertSame([], $riplog->report(), 'riplog-array-report');
        $this->assertSame('valid_log_eac.log', $riplog->filename(), 'riplog-filename');
        $this->assertSame(100, $riplog->score(), 'riplog-score');
        $this->assertSame('EAC', $riplog->ripper(), 'riplog-ripper');
        $this->assertSame('1.3', $riplog->ripperVersion(), 'riplog-ripper-version');
        $this->assertSame('en', $riplog->ripperLanguage(), 'riplog-language');
        $this->assertSame(100, $riplog->score(), 'riplog-score');
        $this->assertGreaterThan(2, strlen($riplog->checkerVersion()), 'riplog-checker-version');

        $this->assertStringEndsWith(
            "<span class='good'>==== Log checksum 302419A0DBDD524D9ADA1079E2699F4C88F5C2FC46D2DA7CC3458BEE556953F5 ====</span>",
            (string)$riplog->html()->get(),
            'riplog-text'
        );

        $this->assertFalse($riplog->isAdjusted(), 'riplog-is-not-adjusted');
        // FIXME: $this->assertNull($riplog->adjusterUserId(), 'riplog-no-adjuster-user-id');
        $this->assertNull($riplog->adjusterUserId(), 'riplog-no-adjuster-user-id');
        $this->assertSame([], $riplog->adjustmentList(), 'riplog-no-adjustment');
        $this->assertSame('', $riplog->adjustment('nothing'), 'riplog-adjustment-nothing');
        $this->assertSame('', $riplog->adjustmentReason(), 'riplog-adjustment-nothing');
    }

    public function testRiplogAdjust(): void {
        $this->user = Helper::makeUser('riplog.' . randomString(6), 'riplog');
        $torrent    = Helper::makeTorrentMusic(
            tgroup: Helper::makeTGroupMusic(
                $this->user,
                'phpunit riplog ' . randomString(6),
                [[ARTIST_MAIN], ['phpunit riplog artist ' . randomString(6)]],
                ['czech']
            ),
            user: $this->user,
        );
        $riplogManager = new Manager\Riplog();
        $riplog = $riplogManager->create(
            $torrent,
            __DIR__ . '/../fixture/valid_log_eac.log',
            'valid_log_eac.log',
        );

        $this->assertNull(
            $riplog->scoreAdjusted(),
            'riplog-score-not-adjusted',
        );
        $this->assertTrue(
            $riplog->setField('checksum_adjust', Enum\RiplogChecksum::invalid)
                ->setField('score_adjust', 33)
                ->setField('id_user_adjust', $this->user->id)
                ->setField('details_adjust', ['phpunit'])
                ->modify(),
            'riplog-modify',
        );
        $this->assertTrue($riplog->flush()->isAdjusted(), 'riplog-is-adjusted');
        $this->assertSame(
            $this->user->id,
            $riplog->adjusterUserId(),
            'riplog-adjuster-user-id'
        );
        $this->assertSame(33, $riplog->scoreAdjusted(), 'riplog-score-adjusted');
        $this->assertFalse($riplog->isAdjustedChecksumOk(), 'riplog-checksum-adjusted');
        $this->assertFalse($riplog->isActualChecksumOk(), 'riplog-checksum-actual');
        $this->assertFalse($riplog->isActualChecksumOk(), 'riplog-checksum-actual');
        $this->assertSame(0, $riplog->adjustmentTrack(''), 'riplog-adjustment-track');
        /*
        $this->assertSame(
            'C2 Pointers enabled',
            $riplog->adjustment('c2_points'),
            'riplog-adjustment-general',
        );
        $this->assertSame(
            '13',
            $riplog->adjustmentTrack('crc_mismatches'),
            'riplog-adjustment-track',
        );
        */
        $riplog->setField('Score', 0)->modify();
        $this->assertSame(
            0,
            $riplogManager->reparse($torrent, $riplog->id + 1),
            'riplog-no-reparse',
        );
        $this->assertSame(
            1,
            $riplogManager->reparse($torrent, $riplog->id),
            'riplog-reparse-ok',
        );
        $this->assertSame(
            100,
            $riplog->flush()->score(),
            'riplog-reparse-score'
        );
    }

    public function testRiplogJsonAdd(): void {
        $this->user = Helper::makeUser('riplog.' . randomString(6), 'riplog');
        $torrent    = Helper::makeTorrentMusic(
            tgroup: Helper::makeTGroupMusic(
                $this->user,
                'phpunit riplog ' . randomString(6),
                [[ARTIST_MAIN], ['phpunit riplog artist ' . randomString(6)]],
                ['czech']
            ),
            user: $this->user,
        );
        $json = new Json\AddLog(
            $torrent,
            [
                'error'    => [UPLOAD_ERR_OK],
                'tmp_name' => [__DIR__ . '/../fixture/valid_log_eac.log'],
                'name'     => ['valid_log_eac.log'],
            ],
        );
        $payload = $json->payload();
        $this->assertSame($torrent->id, $payload['torrentId'], 'json-add-log-tid');
        $this->assertSame(100, $payload['score'], 'json-add-log-score');
        $this->assertTrue($payload['checksum'], 'json-add-log-checksum');
        $summary = $payload['logSummaries'];
        $this->assertCount(1, $summary, 'json-add-log-count-riplog');
        $this->assertSame(100, $summary[0]['score'], 'json-add-log-n-score');
        $this->assertEqualsCanonicalizing(
            ['score', 'checksum', 'ripper', 'ripperVersion', 'language', 'details'],
            array_keys($summary[0]),
            'j́son-add-log-summary-keys',
        );
    }

    public function testRipogModify(): void {
        $this->user = Helper::makeUser('riplog.' . randomString(6), 'riplog');
        $torrent    = Helper::makeTorrentMusic(
            tgroup: Helper::makeTGroupMusic(
                $this->user,
                'phpunit riplog ' . randomString(6),
                [[ARTIST_MAIN], ['phpunit riplog artist ' . randomString(6)]],
                ['czech']
            ),
            user: $this->user,
        );
        $manager = new Manager\Riplog();
        $riplog  = $manager->create(
            $torrent,
            __DIR__ . '/../fixture/valid_log_eac.log',
            'valid_log_eac.log',
        );
        $this->assertTrue(
            $riplog->setField('checksum_adjust', Enum\RiplogChecksum::invalid)
                ->setField('score_adjust', 33)
                ->setField('id_user_adjust', $this->user->id)
                ->setField('reason_adjust', 'phpunit')
                ->setField('details_adjust', ['score was too high'])
                ->modify(),
            'riplog-adjust-modify',
        );
        $this->assertTrue(
            $riplog->isAdjusted(),
            'riplog-adjust-is-adjusted',
        );
        $this->assertSame(
            Enum\RiplogChecksum::invalid,
            $riplog->checksumAdjusted(),
            'riplog-adjust-checksum-read',
        );
        $this->assertSame(
            33,
            $riplog->scoreAdjusted(),
            'riplog-adjust-score-read',
        );
        $this->assertTrue(
            $riplog->setField('score_adjust', 44)
                ->modify(),
            'riplog-adjust-update',
        );
        $riplog->flush();
        $this->assertTrue(
            $riplog->isAdjusted(),
            'riplog-adjust-is-still-adjusted',
        );
        $this->assertSame(
            44,
            $riplog->scoreAdjusted(),
            'riplog-adjust-new-score',
        );
        $this->assertSame(
            1,
            $manager->reparse($torrent, $riplog->id),
            'riplog-reparse',
        );
        $riplog->flush();
        $this->assertFalse(
            $riplog->isAdjusted(),
            'riplog-reparsed-is-unadjusted',
        );
        $this->assertNull(
            $riplog->scoreAdjusted(),
            'riplog-score-reparsed',
        );
    }

    public function testRiplogRemove(): void {
        $this->user = Helper::makeUser('riplog.' . randomString(6), 'riplog');
        $torrent    = Helper::makeTorrentMusic(
            tgroup: Helper::makeTGroupMusic(
                $this->user,
                'phpunit riplog ' . randomString(6),
                [[ARTIST_MAIN], ['phpunit riplog artist ' . randomString(6)]],
                ['czech']
            ),
            user: $this->user,
        );
        $manager = new Manager\Riplog();
        $riplog  = $manager->create(
            $torrent,
            __DIR__ . '/../fixture/valid_log_eac.log',
            'valid_log_eac.log',
        );
        $path = $riplog->file()->path();
        $this->assertTrue(file_exists($path), 'riplog-file-exists');
        $this->assertSame(1, $riplog->remove(), 'riplog-remove');
        $this->assertFalse(file_exists($path), 'riplog-file-gone');

        $manager->create(
            $torrent,
            __DIR__ . '/../fixture/valid_log_eac.log',
            'valid_log_eac.log',
        );
        $this->assertSame(
            1,
            $manager->removeAll($torrent, $this->user),
            'riplog-manager-remove-all',
        );
    }
}
