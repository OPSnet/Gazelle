<?php

namespace Gazelle\Schedule\Tasks;

class TorrentHistory extends \Gazelle\Schedule\Task
{
    public function run()
    {
        (new \Gazelle\Manager\Torrent)->updateSeedingHistory();
    }
}
