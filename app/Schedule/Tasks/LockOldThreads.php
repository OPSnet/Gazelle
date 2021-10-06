<?php

namespace Gazelle\Schedule\Tasks;

class LockOldThreads extends \Gazelle\Schedule\Task
{
    public function run()
    {
        $this->processed = (new \Gazelle\Manager\Forum)->lockOldThreads();
    }
}
