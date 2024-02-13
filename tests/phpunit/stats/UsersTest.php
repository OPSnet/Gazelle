<?php

use PHPUnit\Framework\TestCase;

require_once(__DIR__ . '/../../../lib/bootstrap.php');

class UsersTest extends TestCase {
    public function testStats(): void {
        $stats = new \Gazelle\Stats\Users;

        /* not easy to test precise results, but at least the SQL can be exercised */
        $this->assertIsArray($stats->browserDistribution(), 'users-stats-browser');
        $this->assertIsArray($stats->browserDistributionList(), 'users-stats-browser-list');
        $this->assertIsArray($stats->userclassDistribution(), 'users-stats-userclass');
        $this->assertIsArray($stats->userclassDistributionList(), 'users-stats-userclass-list');
        $this->assertIsArray($stats->platformDistribution(), 'users-stats-platform');
        $this->assertIsArray($stats->platformDistributionList(), 'users-stats-platform-list');
        $this->assertIsArray($stats->geodistribution(), 'users-stats-geodistribution');
        $this->assertIsInt($stats->leecherTotal(), 'users-stats-total-leecher');
        $this->assertIsInt($stats->peerTotal(), 'users-stats-total-peer');
        $this->assertIsInt($stats->seederTotal(), 'users-stats-total-seeder');
        $this->assertIsInt($stats->snatchTotal(), 'users-stats-total-snatch');

        $this->assertCount(3, $stats->peerStat(), 'users-stats-peer');
        $this->assertCount(24, $stats->flow(), 'users-stats-flow');
    }

    public function testTop(): void {
        $stats = new \Gazelle\Stats\Users;
        $this->assertInstanceOf(Gazelle\Stats\Users::class, $stats->flush(), 'users-stats-flush');
        $this->assertInstanceOf(Gazelle\Stats\Users::class, $stats->flushTop(10), 'users-stats-top-flush');
        $this->assertIsArray($stats->topDownloadList(10), 'users-stats-top-download');
        $this->assertIsArray($stats->topDownSpeedList(10), 'users-stats-top-downspeed');
        $this->assertIsArray($stats->topUploadList(10), 'users-stats-top-upload');
        $this->assertIsArray($stats->topUpSpeedList(10), 'users-stats-top-upspeed');
        $this->assertIsArray($stats->topTotalUploadList(10), 'users-stats-top-total-upload');
    }

    public function testAjaxTop10(): void {
        $bogus = new Gazelle\Json\Top10\User(
            'bogus',
            10,
            new \Gazelle\Stats\Users,
            new \Gazelle\Manager\User,
        );
        $this->assertCount(0, $bogus->payload(), 'user-ajax-top10-bogus');

        $all = new Gazelle\Json\Top10\User(
            'all',
            10,
            new \Gazelle\Stats\Users,
            new \Gazelle\Manager\User,
        );
        $this->assertCount(5, $all->payload(), 'user-ajax-top10-all');

        $ul = new Gazelle\Json\Top10\User(
            'ul',
            10,
            new \Gazelle\Stats\Users,
            new \Gazelle\Manager\User,
        );
        $this->assertCount(1, $ul->payload(), 'user-ajax-top10-ul');
    }
}
