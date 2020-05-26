<?php

namespace Gazelle\Schedule\Tasks;

class UpdateUserBonusPoints extends \Gazelle\Schedule\Task
{
    public function run()
    {
        $bonus = new \Gazelle\Bonus;
        $this->processed = $bonus->givePoints($this);
    }
}
