<?php

namespace Gazelle;

use PHPUnit\Framework\TestCase;
use GazelleUnitTest\Helper;
use Gazelle\Enum\UserStatus;

class UsersTest extends TestCase {
    protected array $userList;

    public function tearDown(): void {
        if (isset($this->userList)) {
            foreach ($this->userList as $user) {
                $user?->remove();
            }
        }
    }

    public function testUserStats(): void {
        $this->userList[] = Helper::makeUser('stats.' . randomString(10), 'user', enable: true);
        $this->userList[0]->setField('ipcc', 'XD')->modify();
        $stats = new Stats\Users();
        $this->assertGreaterThanOrEqual(0, $stats->refresh(), 'user-stats-refresh');

        /* not easy to test precise results, but at least the SQL can be exercised */
        $this->assertGreaterThanOrEqual(0, count($stats->browserDistribution(true)), 'users-stats-browser');
        $this->assertGreaterThanOrEqual(0, count($stats->browserDistributionList(true)), 'users-stats-browser-list');

        $dist = $stats->userclassDistribution(true);
        $this->assertCount(14, $dist, 'users-stats-userclass-total');
        $this->assertEquals('User', $dist[0]['name'], 'users-stats-userclass-name');
        $this->assertGreaterThan(0, $dist[0]['y'], 'users-stats-userclass-total');

        $list = $stats->userclassDistributionList(true);
        $this->assertCount(14, $list, 'users-stats-userclassdist-total');
        $this->assertGreaterThan(0, $list['User'], 'users-stats-userclassdist-total');

        $this->assertGreaterThanOrEqual(0, count($stats->platformDistribution(true)), 'users-stats-platform');
        $this->assertGreaterThanOrEqual(0, count($stats->platformDistributionList(true)), 'users-stats-platform-list');
        $this->assertGreaterThanOrEqual(0, count($stats->browserList()), 'user-stats-browser');
        $this->assertGreaterThanOrEqual(0, count($stats->operatingSystemList()), 'user-stats-os');

        $this->assertGreaterThanOrEqual(0, $stats->leecherTotal(), 'users-stats-total-leecher');
        $this->assertGreaterThanOrEqual(0, $stats->peerTotal(), 'users-stats-total-peer');
        $this->assertGreaterThanOrEqual(0, $stats->seederTotal(), 'users-stats-total-seeder');
        $this->assertGreaterThanOrEqual(0, $stats->snatchTotal(), 'users-stats-total-snatch');
        $this->assertEquals(
            ['peer_total', 'seeder_total', 'leecher_total'],
            array_keys($stats->peerStat()),
            'users-stats-peer'
        );

        $this->assertGreaterThanOrEqual(0, count($stats->stockpileTokenList(10)), 'user-stats-stockpile');
        $this->assertCount(24, $stats->flow(), 'users-stats-flow');

        $this->assertGreaterThan(0, $stats->registerActivity('users_stats_daily', 10), 'user-stats-register');
        $this->assertGreaterThan(0, $stats->enabledUserTotal(), 'user-stats-enabled');
        $this->assertGreaterThan(0, count($stats->activityStat()), 'user-stats-activity');
        $this->assertGreaterThanOrEqual(0, $stats->dayActiveTotal(), 'user-stats-active-day');
        $this->assertGreaterThanOrEqual(0, $stats->weekActiveTotal(), 'user-stats-active-week');
        $this->assertGreaterThanOrEqual(0, $stats->monthActiveTotal(), 'user-stats-active-month');
    }

    public function testGeodistribution(): void {
        $stats = new Stats\Users();
        $stats->flush();
        $this->userList[] = Helper::makeUser('geodist.' . randomString(10), 'user');
        $this->userList[0]->setField('ipcc', 'XA')->setField('PermissionID', SYSOP)->modify();
        foreach (range(1, COUNTRY_MINIMUM + 1) as $n) {
            $user = Helper::makeUser('geodist.' . randomString(10), 'user');
            $user->setField('ipcc', 'XB')->modify();
            $this->userList[] = $user;
        }
        $geodist = $stats->geodistribution();
        $this->assertGreaterThanOrEqual(0, count($geodist), 'users-stats-geodistribution');
        $ipccList = array_map(fn($c) => $c['ipcc'], $geodist);

        // If any the following tests fail, it is likely due to artifacts left over from previous tests
        $this->assertContains('XA', $ipccList, 'ustats-geodist-XA');
        $this->assertContains('XB', $ipccList, 'ustats-geodist-XB');
        $this->assertGreaterThan($geodist[1]['staff'], $geodist[0]['staff'], 'ustats-geodist-XA-gt-XB');

        $geoStaff = array_values(array_filter(
            $stats->geodistributionChart($this->userList[0]),
            fn ($c) => $c['ipcc'] === 'XB'
        ));
        $geoPublic = array_values(array_filter(
            $stats->geodistributionChart($this->userList[1]),
            fn ($c) => $c['ipcc'] === 'XB'
        ));
        $this->assertGreaterThan($geoStaff[0]['value'], $geoPublic[0]['value'], 'ustats-geodist-staff');
        $this->assertEquals(COUNTRY_MINIMUM + COUNTRY_STEP, $geoPublic[0]['value'], 'ustats-geodist-public');
    }

    public function testNewUsersAllowed(): void {
        $stats = new Stats\Users();
        $this->userList[] = Helper::makeUser('stats.' . randomString(6), 'user', enable: true);
        $this->assertFalse($stats->overUsercap(), 'user-stats-over-usercap');
        $this->assertTrue($stats->newUsersAllowed($this->userList[0]), 'user-stats-new-users');
    }

    public function testMiscUserStats(): void {
        $this->userList[] = Helper::makeUser('stats.' . randomString(6), 'user', enable: true);
        $userStats = $this->userList[0]->stats();
        $id = $this->userList[0]->id;
        $this->assertEquals(
            "user.php?action=stats&userid={$id}",
            $userStats->location(),
            'user-stats-location',
        );
        $this->assertEquals(
            "<a href=\"user.php?action=stats&amp;userid={$id}\">Stats</a>",
            $userStats->link(),
            'user-stats-link'
        );
        $this->assertEquals(
            0,
            $userStats->unresolvedReportsTotal(),
            'user-stats-unresolved-reports',
        );
        $this->assertEquals(
            0,
            $userStats->remove(),
            'user-stats-remove',
        );
    }

    public function testTop(): void {
        $stats = new Stats\Users();
        $this->assertInstanceOf(Stats\Users::class, $stats->flush(), 'users-stats-flush');
        $this->assertInstanceOf(Stats\Users::class, $stats->flushTop(10), 'users-stats-top-flush');
        $this->assertGreaterThanOrEqual(0, count($stats->topDownloadList(10)), 'users-stats-top-download');
        $this->assertGreaterThanOrEqual(0, count($stats->topDownSpeedList(10)), 'users-stats-top-downspeed');
        $this->assertGreaterThanOrEqual(0, count($stats->topUploadList(10)), 'users-stats-top-upload');
        $this->assertGreaterThanOrEqual(0, count($stats->topUpSpeedList(10)), 'users-stats-top-upspeed');
        $this->assertGreaterThanOrEqual(0, count($stats->topTotalUploadList(10)), 'users-stats-top-total-upload');
    }

    public function testAjaxTop10(): void {
        $bogus = new Json\Top10\User('bogus', 10);
        $this->assertCount(0, $bogus->payload(), 'user-ajax-top10-bogus');

        $all = new Json\Top10\User('all', 10);
        $this->assertCount(5, $all->payload(), 'user-ajax-top10-all');

        $ul = new Json\Top10\User('ul', 10);
        $this->assertCount(1, $ul->payload(), 'user-ajax-top10-ul');
    }

    public function testEcoStats(): void {
        $this->userList[0] = Helper::makeUser('stats.' . randomString(6), 'user', enable: true);

        $eco = new Stats\Economic();
        $eco->flush();

        $total    = $eco->tokenTotal();
        $stranded = $eco->tokenStrandedTotal();
        $this->assertTrue($this->userList[0]->updateTokens(23), 'utest-stats-token-5');

        $eco->flush();
        $this->assertEquals(23 + $total, $eco->tokenTotal(), 'utest-stats-total-tokens');
        $this->assertEquals($stranded, $eco->tokenStrandedTotal(), 'utest-stats-total-stranded-tokens');

        $disabled = $eco->userDisabledTotal();
        $eco->flush();
        $this->userList[0]->setField('Enabled', UserStatus::disabled->value)->modify();

        $this->assertEquals(23 + $stranded, $eco->tokenStrandedTotal(), 'utest-stats-total-disabled-stranded-tokens');
        $this->assertEquals(1 + $disabled, $eco->userDisabledTotal(), 'utest-stats-user-disabled-total');
    }

    public function testJsonUserStats(): void {
        $this->userList[] = Helper::makeUser('stats.' . randomString(10), 'json', enable: true);
        $this->assertEqualsCanonicalizing(
            [
                'flow',
                'browsers',
                'classes',
                'platforms',
            ],
            array_keys(new Json\Stats\User($this->userList[0])->payload()),
            'json-stats-users',
        );
    }
}
