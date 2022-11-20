<?php

namespace Gazelle\Schedule\Tasks;

class Peerupdate extends \Gazelle\Schedule\Task {
    public function run() {
        [$updated, $skipped] = (new \Gazelle\Manager\Torrent)->updatePeerlists();
        $this->processed += $updated;
    }
}

