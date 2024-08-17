<?php

use PHPUnit\Framework\TestCase;

class EconomicTest extends TestCase {
    public function testEconomic(): void {
        $eco = new \Gazelle\Stats\Economic();

        $this->assertIsInt($eco->bountyAvailable(), 'eco-stats-bounty-available');
        $this->assertIsInt($eco->bountyTotal(), 'eco-stats-bounty-total');
        $this->assertIsInt($eco->downloadTotal(), 'eco-stats-download-total');
        $this->assertIsInt($eco->leecherTotal(), 'eco-stats-leecher-total');
        $this->assertIsInt($eco->peerTotal(), 'eco-stats-peer-total');
        $this->assertIsInt($eco->seederTotal(), 'eco-stats-seeder-total');
        $this->assertIsInt($eco->snatchGrandTotal(), 'eco-stats-seeder-grand-total');
        $this->assertIsInt($eco->snatchTotal(), 'eco-stats-snatch-total');
        $this->assertIsInt($eco->torrentTotal(), 'eco-stats-total-total');
        $this->assertIsInt($eco->uploadTotal(), 'eco-stats-upload-total');
        $this->assertIsInt($eco->userTotal(), 'eco-stats-user-total');
        $this->assertIsInt($eco->userMfaTotal(), 'eco-stats-user-mfa-total');
        $this->assertIsInt($eco->userPeerTotal(), 'eco-stats-user-peer-total');
    }
}
