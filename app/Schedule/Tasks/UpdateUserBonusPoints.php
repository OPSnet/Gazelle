<?php

namespace Gazelle\Schedule\Tasks;

class UpdateUserBonusPoints extends \Gazelle\Schedule\Task
{
    public function run()
    {
        $bonus = new \Gazelle\Bonus($this->db, $this->cache);
        $this->processed = $bonus->givePoints();

    }
}
