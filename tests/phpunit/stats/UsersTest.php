<?php

namespace Gazelle;

use PHPUnit\Framework\TestCase;
use Gazelle\Enum\UserStatus;

class UsersTest extends TestCase {
    protected array $userList;

    public function tearDown(): void {
        if (isset($this->userList)) {
            foreach ($this->userList as $user) {
                if (isset($user)) {
                    DB::DB()->prepared_query("
                        DELETE FROM users_stats_daily WHERE UserID = ?
                        ", $user->id()
                    );
                    $user->remove();
                }
            }
        }
    }

    public function testUserStats(): void {
        $stats = new Stats\Users();

        /* not easy to test precise results, but at least the SQL can be exercised */
        $this->assertIsArray($stats->browserDistribution(), 'users-stats-browser');
        $this->assertIsArray($stats->browserDistributionList(), 'users-stats-browser-list');
        $this->assertIsArray($stats->userclassDistribution(), 'users-stats-userclass');
        $this->assertIsArray($stats->userclassDistributionList(), 'users-stats-userclass-list');
        $this->assertIsArray($stats->platformDistribution(), 'users-stats-platform');
        $this->assertIsArray($stats->platformDistributionList(), 'users-stats-platform-list');
        $this->assertIsArray($stats->browserList(), 'user-stats-browser');
        $this->assertIsArray($stats->operatingSystemList(), 'user-stats-os');

        $this->assertIsInt($stats->leecherTotal(), 'users-stats-total-leecher');
        $this->assertIsInt($stats->peerTotal(), 'users-stats-total-peer');
        $this->assertIsInt($stats->seederTotal(), 'users-stats-total-seeder');
        $this->assertIsInt($stats->snatchTotal(), 'users-stats-total-snatch');
        $this->assertCount(3, $stats->peerStat(), 'users-stats-peer');

        $this->assertIsArray($stats->stockpileTokenList(10), 'user-stats-stockpile');
        $this->assertCount(24, $stats->flow(), 'users-stats-flow');

        // will be zero on a fresh install
        $this->assertGreaterThanOrEqual(0, $stats->refresh(), 'user-stats-refresh');
        $this->assertGreaterThan(0, $stats->registerActivity('users_stats_daily', 10), 'user-stats-register');

        $this->assertIsInt($stats->enabledUserTotal(), 'user-stats-enabled');

        $this->assertIsArray($stats->activityStat(), 'user-stats-activity');
        $this->assertIsInt($stats->dayActiveTotal(), 'user-stats-active-day');
        $this->assertIsInt($stats->weekActiveTotal(), 'user-stats-active-week');
        $this->assertIsInt($stats->monthActiveTotal(), 'user-stats-active-month');
    }

    public function testGeodistribution(): void {
        $stats = new Stats\Users();
        $stats->flush();
        $this->userList[] = \GazelleUnitTest\Helper::makeUser('geodist.' . randomString(10), 'user');
        $this->userList[0]->setField('ipcc', 'XA')->setField('PermissionID', SYSOP)->modify();
        foreach (range(1, COUNTRY_MINIMUM + 1) as $n) {
            $user = \GazelleUnitTest\Helper::makeUser('geodist.' . randomString(10), 'user');
            $user->setField('ipcc', 'XB')->modify();
            $this->userList[] = $user;
        }
        $geodist = $stats->geodistribution();
        $this->assertIsArray($geodist, 'users-stats-geodistribution');
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
        $this->userList[] = \GazelleUnitTest\Helper::makeUser('stats.' . randomString(6), 'user', enable: true);
        $this->assertTrue($stats->newUsersAllowed($this->userList[0]), 'user-stats-new-users');
    }

    public function testTop(): void {
        $stats = new Stats\Users();
        $this->assertInstanceOf(Stats\Users::class, $stats->flush(), 'users-stats-flush');
        $this->assertInstanceOf(Stats\Users::class, $stats->flushTop(10), 'users-stats-top-flush');
        $this->assertIsArray($stats->topDownloadList(10), 'users-stats-top-download');
        $this->assertIsArray($stats->topDownSpeedList(10), 'users-stats-top-downspeed');
        $this->assertIsArray($stats->topUploadList(10), 'users-stats-top-upload');
        $this->assertIsArray($stats->topUpSpeedList(10), 'users-stats-top-upspeed');
        $this->assertIsArray($stats->topTotalUploadList(10), 'users-stats-top-total-upload');
    }

    public function testAjaxTop10(): void {
        $bogus = new Json\Top10\User(
            'bogus',
            10,
            new Stats\Users(),
            new Manager\User(),
        );
        $this->assertCount(0, $bogus->payload(), 'user-ajax-top10-bogus');

        $all = new Json\Top10\User(
            'all',
            10,
            new Stats\Users(),
            new Manager\User(),
        );
        $this->assertCount(5, $all->payload(), 'user-ajax-top10-all');

        $ul = new Json\Top10\User(
            'ul',
            10,
            new Stats\Users(),
            new Manager\User(),
        );
        $this->assertCount(1, $ul->payload(), 'user-ajax-top10-ul');
    }

    public function testEcoStats(): void {
        $stats = new Stats\Users();
        $this->userList[0] = \GazelleUnitTest\Helper::makeUser('stats.' . randomString(6), 'user', enable: true);

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
        // $stats->refresh();

        $this->assertEquals(23 + $stranded, $eco->tokenStrandedTotal(), 'utest-stats-total-disabled-stranded-tokens');
        $this->assertEquals(1 + $disabled, $eco->userDisabledTotal(), 'utest-stats-user-disabled-total');
    }
}
