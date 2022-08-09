<?php

namespace Gazelle\Schedule\Tasks;

class ArtistUsage extends \Gazelle\Schedule\Task {
    public function run() {
        $this->processed = (new \Gazelle\Stats\Artists)->updateUsage();
    }
}
