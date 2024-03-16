<?php

namespace Gazelle\Task;

class CalculateContestLeaderboard extends \Gazelle\Task {
    public function run(): void {
        $contestMan = new \Gazelle\Manager\Contest();
        $this->processed = $contestMan->calculateAllLeaderboards();
        $this->processed += $contestMan->schedulePayout();
    }
}
