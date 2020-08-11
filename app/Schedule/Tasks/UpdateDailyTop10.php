<?php

namespace Gazelle\Schedule\Tasks;

class UpdateDailyTop10 extends \Gazelle\Schedule\Task
{
    public function run()
    {
        $torrents = new \Gazelle\Top10\Torrent([], []);
        $torrents->storeTop10('Daily', 'day', 1);
    }
}
