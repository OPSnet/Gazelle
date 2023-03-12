<?php

namespace Gazelle\Schedule\Tasks;

class DeleteUnseededTorrents extends \Gazelle\Schedule\Task {
    public function run(): void {
        $torrents = new \Gazelle\Torrent\Reaper;

        $deleted = $torrents->deleteDeadTorrents(true, false);
        foreach ($deleted as $id) {
            $this->debug("Deleted torrent $id", $id);
            $this->processed++;
        }
    }
}
