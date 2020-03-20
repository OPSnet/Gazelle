<?php

namespace Gazelle\Schedule\Tasks;

class CalculateContestLeaderboard extends \Gazelle\Schedule\Task
{
    public function run()
    {
        $contestMgr = new \Gazelle\Contest($this->db, $this->cache);

        $contestMgr->calculate_leaderboard();
        $contestMgr->calculate_request_pairs();

        $this->processed = $contestMgr->schedule_payout();
    }
}
