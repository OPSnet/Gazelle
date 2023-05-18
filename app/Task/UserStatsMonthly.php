<?php

namespace Gazelle\Task;

class UserStatsMonthly extends \Gazelle\Task {
    public function run(): void {
        $this->processed += (new \Gazelle\Stats\Users)
            ->registerActivity('users_stats_monthly', DELETE_USER_STATS_MONTHLY_DAY);
    }
}
