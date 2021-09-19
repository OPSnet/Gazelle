<?php

namespace Gazelle\Schedule\Tasks;

class UpdateUserBonusPoints extends \Gazelle\Schedule\Task
{
    public function run()
    {
        $this->processed = (new \Gazelle\Manager\Bonus)->givePoints($this);
    }
}
