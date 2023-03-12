<?php

namespace Gazelle\Schedule\Tasks;

class DeleteNeverSeededTorrents extends \Gazelle\Schedule\Task {
    public function run(): void {
        $torrents = new \Gazelle\Torrent\Reaper;

        $deleted = $torrents->deleteDeadTorrents(false, true);
        foreach ($deleted as $id) {
            $this->debug("Deleted torrent $id", $id);
            $this->processed++;
        }
    }
}
