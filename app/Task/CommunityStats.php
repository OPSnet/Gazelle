<?php

namespace Gazelle\Task;

use Gazelle\Stats\Users   as UsersStats;
use Gazelle\Stats\TGroups as TGroupsStats;

class CommunityStats extends \Gazelle\Task {
    public function run(): void {
        $tgroupRefresh = new TGroupsStats()->refresh();
        $this->info("tgroup", $tgroupRefresh);

        $usersStats     = new UsersStats();
        $trackerRefresh = $usersStats->refreshUseragentTracker();
        $this->info("tracker", $trackerRefresh);
        $this->processed = $usersStats->refresh($this) + $tgroupRefresh + $trackerRefresh;
    }
}
