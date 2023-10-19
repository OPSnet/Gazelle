<?php

use PHPUnit\Framework\TestCase;

require_once(__DIR__ . '/../../../lib/bootstrap.php');

class UsersTest extends TestCase {
    public function testStats(): void {
        $stats = new \Gazelle\Stats\Users;

        /* not easy to test precise results, but at least the SQL can be exercised */
        $this->assertIsArray($stats->flow(), 'stats-users-flow');
        $this->assertIsArray($stats->browserDistribution(), 'stats-users-browser');
        $this->assertIsArray($stats->browserDistributionList(), 'stats-users-browser-list');
        $this->assertIsArray($stats->userclassDistribution(), 'stats-users-userclass');
        $this->assertIsArray($stats->userclassDistributionList(), 'stats-users-userclass-list');
        $this->assertIsArray($stats->platformDistribution(), 'stats-users-platform');
        $this->assertIsArray($stats->platformDistributionList(), 'stats-users-platform-list');
        $this->assertIsArray($stats->geodistribution(), 'stats-users-geodistribution');
        $this->assertCount(3, $stats->peerStat(), 'stats-users-peer');
        $this->assertIsInt($stats->leecherTotal(), 'stats-users-total-leecher');
        $this->assertIsInt($stats->peerTotal(), 'stats-users-total-peer');
        $this->assertIsInt($stats->seederTotal(), 'stats-users-total-seeder');
        $this->assertIsInt($stats->snatchTotal(), 'stats-users-total-snatch');
    }

    public function testTop(): void {
        $stats = new \Gazelle\Stats\Users;
        $this->assertInstanceOf(Gazelle\Stats\Users::class, $stats->flush(), 'stats-users-flush');
        $this->assertInstanceOf(Gazelle\Stats\Users::class, $stats->flushTop(10), 'stats-users-top-flush');
        $this->assertIsArray($stats->topDownloadList(10), 'stats-users-top-download');
        $this->assertIsArray($stats->topDownSpeedList(10), 'stats-users-top-downspeed');
        $this->assertIsArray($stats->topUploadList(10), 'stats-users-top-upload');
        $this->assertIsArray($stats->topUpSpeedList(10), 'stats-users-top-upspeed');
        $this->assertIsArray($stats->topTotalUploadList(10), 'stats-users-top-total-upload');
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
