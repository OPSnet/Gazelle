<?php

namespace Gazelle\Task;

class ArtistUsage extends \Gazelle\Task {
    public function run(): void {
        $this->processed = (new \Gazelle\Stats\Artists)->updateUsage();
    }
}
