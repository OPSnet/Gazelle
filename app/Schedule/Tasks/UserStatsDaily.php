<?php

namespace Gazelle\Schedule\Tasks;

class UserStatsDaily extends \Gazelle\Schedule\Task {
    public function run(): void {
        $this->processed += (new \Gazelle\Stats\Users)
            ->registerActivity('users_stats_daily', DELETE_USER_STATS_DAILY_DAY);
    }
}
