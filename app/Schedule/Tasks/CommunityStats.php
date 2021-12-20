<?php

namespace Gazelle\Schedule\Tasks;

class CommunityStats extends \Gazelle\Schedule\Task
{
    public function run()
    {
        $this->processed = (new \Gazelle\Stats\Users)->refresh()
            + (new \Gazelle\Stats\TGroups)->refresh();
    }
}
