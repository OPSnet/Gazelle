<?php

namespace Gazelle\Schedule\Tasks;

class UpdateWeeklyTop10 extends \Gazelle\Schedule\Task
{
    public function run()
    {
        $this->processed = (new \Gazelle\Manager\Torrent)->storeTop10('Weekly', 'week', 7);
    }
}
