<?php

namespace Gazelle\Schedule\Tasks;

class CalculateContestLeaderboard extends \Gazelle\Schedule\Task
{
    public function run()
    {
        $contestMan = new \Gazelle\Manager\Contest;
        $this->processed = $contestMan->calculateAllLeaderboards();
        $this->processed += $contestMan->schedulePayout();
    }
}
