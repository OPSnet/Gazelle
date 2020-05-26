<?php

namespace Gazelle\Schedule\Tasks;

class DeleteNeverSeededTorrents extends \Gazelle\Schedule\Task
{
    public function run()
    {
        $torrents = new \Gazelle\TorrentReaper;

        $deleted = $torrents->deleteDeadTorrents(false, true);
        foreach ($deleted as $id) {
            $this->debug("Deleted torrent $id", $id);
            $this->processed++;
        }
    }
}
