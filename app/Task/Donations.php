<?php

namespace Gazelle\Task;

class Donations extends \Gazelle\Task {
    public function run(): void {
        $donorMan = new \Gazelle\Manager\Donation;
        $this->processed = $donorMan->expireRanks();
    }
}
