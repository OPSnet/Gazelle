<?php

namespace Gazelle\Task;

class Peerupdate extends \Gazelle\Task {
    public function run(): void {
        [$updated, $skipped] = (new \Gazelle\Manager\Torrent)->updatePeerlists();
        $this->processed += $updated;
    }
}

