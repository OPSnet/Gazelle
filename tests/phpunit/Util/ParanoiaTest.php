<?php

namespace Gazelle\Util;

use PHPUnit\Framework\TestCase;

class ParanoiaTest extends TestCase {
    public function testEverythingVisible(): void {
        $this->assertSame(
            [],
            Paranoia::calculate([
                'p_downloaded' => 'on', 'p_uploaded' => 'on', 'p_ratio' => 'on', 'p_bonuspoints' => 'on',
                'p_lastseen' => 'on', 'p_requiredratio' => 'on', 'p_invitedcount' => 'on', 'p_artistsadded' => 'on',
                'p_notifications' => 'on', 'p_donor_heart' => 'on',
                'p_l_torrentcomments' => 'on', 'p_l_collages' => 'on', 'p_l_collagecontribs' => 'on',
                'p_l_uploads' => 'on', 'p_l_uniquegroups' => 'on', 'p_l_perfectflacs' => 'on',
                'p_l_seeding' => 'on', 'p_l_leeching' => 'on', 'p_l_snatched' => 'on',
                'p_list_requestsfilled' => 'on', 'p_count_requestsfilled' => 'on', 'p_bounty_requestsfilled' => 'on',
                'p_list_requestsvoted' => 'on', 'p_count_requestsvoted' => 'on', 'p_bounty_requestsvoted' => 'on',
            ]),
            'paranoia-none-hidden'
        );
    }

    public function testNothingChecked(): void {
        $paranoia = Paranoia::calculate([]);
        $this->assertContains('downloaded', $paranoia, 'paranoia-downloaded');
        $this->assertContains('uploads+', $paranoia, 'paranoia-uploads-count-only');
        $this->assertContains('hide_donor_heart', $paranoia, 'paranoia-donor-heart');
        $this->assertContains('requestsfilled_list', $paranoia, 'paranoia-requestsfilled-list');
    }

    public function testListShownWithoutCount(): void {
        // Showing the list implies the count is also shown.
        $paranoia = Paranoia::calculate(['p_l_uploads' => 'on']);
        $this->assertNotContains('uploads', $paranoia, 'paranoia-uploads-list-shown');
        $this->assertNotContains('uploads+', $paranoia, 'paranoia-uploads-count-implied');
        // p_l_uploads also forces uniquegroups/perfectflacs list+count and artistsadded visible.
        $this->assertNotContains('uniquegroups', $paranoia, 'paranoia-uniquegroups-cascade');
        $this->assertNotContains('perfectflacs', $paranoia, 'paranoia-perfectflacs-cascade');
        $this->assertNotContains('artistsadded', $paranoia, 'paranoia-artistsadded-cascade');
    }

    public function testCountOnlyShown(): void {
        $paranoia = Paranoia::calculate(['p_c_uploads' => 'on']);
        $this->assertContains('uploads', $paranoia, 'paranoia-uploads-list-hidden');
        $this->assertNotContains('uploads+', $paranoia, 'paranoia-uploads-count-shown');
    }

    public function testTwoOfThreeStatsShowsAll(): void {
        $paranoia = Paranoia::calculate(['p_downloaded' => 'on', 'p_uploaded' => 'on']);
        $this->assertNotContains('downloaded', $paranoia, 'paranoia-stats-cascade-downloaded');
        $this->assertNotContains('uploaded', $paranoia, 'paranoia-stats-cascade-uploaded');
        $this->assertNotContains('ratio', $paranoia, 'paranoia-stats-cascade-ratio');
    }

    public function testListInputIsNormalized(): void {
        $this->assertSame(
            Paranoia::calculate(['p_downloaded' => 'on']),
            Paranoia::calculate(['p_downloaded']),
            'paranoia-list-input-normalized'
        );
    }
}
