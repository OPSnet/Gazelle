<?php

namespace Gazelle\Schedule\Tasks;

class Donations extends \Gazelle\Schedule\Task {
    public function run(): void {
        $donorMan = new \Gazelle\Manager\Donation;
        $this->processed = $donorMan->expireRanks();
    }
}
