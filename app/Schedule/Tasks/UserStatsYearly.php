<?php

namespace Gazelle\Schedule\Tasks;

class UserStatsYearly extends \Gazelle\Schedule\Task {
    public function run(): void {
        $this->processed += (new \Gazelle\Stats\Users)
            ->registerActivity('users_stats_yearly', 0);
    }
}
