<?php

namespace Gazelle\Schedule\Tasks;

class UserStatsMonthly extends \Gazelle\Schedule\Task
{
    public function run()
    {
        $this->processed += (new \Gazelle\Stats\Users)
            ->registerActivity('users_stats_monthly', DELETE_USER_STATS_MONTHLY_DAY);
    }
}
