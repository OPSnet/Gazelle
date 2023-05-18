<?php

namespace Gazelle\Task;

class UserStatsYearly extends \Gazelle\Task {
    public function run(): void {
        $this->processed += (new \Gazelle\Stats\Users)
            ->registerActivity('users_stats_yearly', 0);
    }
}
