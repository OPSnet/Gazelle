<?php

namespace Gazelle\Task;

class TorrentHistory extends \Gazelle\Task {
    public function run(): void {
        (new \Gazelle\Manager\Torrent())->updateSeedingHistory();
    }
}
