<?php

namespace Gazelle\Task;

class CommunityStats extends \Gazelle\Task {
    public function run(): void {
        $this->processed = (new \Gazelle\Stats\Users)->refresh()
            + (new \Gazelle\Stats\TGroups)->refresh();
    }
}
