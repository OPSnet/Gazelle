<?php

namespace Gazelle\Schedule\Tasks;

class Donations extends \Gazelle\Schedule\Task
{
    public function run()
    {
        // yikes
        \Donations::schedule();
    }
}
