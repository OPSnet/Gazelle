<?php

namespace Gazelle\Schedule\Tasks;

class UpdateWeeklyTop10 extends \Gazelle\Schedule\Task
{
    public function run()
    {
        $torrents = new \Gazelle\Top10\Torrent([], []);
        $torrents->storeTop10('Weekly', 'week', 7);
    }
}
