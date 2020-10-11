<?php

namespace Gazelle\Schedule\Tasks;

class RemoveDeadSessions extends \Gazelle\Schedule\Task
{
    public function run()
    {
        $sessionMan = new \Gazelle\Session(0);
        $this->processed += $sessionMan->purgeDead();
    }
}
