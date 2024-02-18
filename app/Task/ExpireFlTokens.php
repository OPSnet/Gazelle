<?php

namespace Gazelle\Task;

class ExpireFlTokens extends \Gazelle\Task {
    public function run(): void {
        $this->processed = (new \Gazelle\Manager\User())
            ->expireFreeleechTokens(new \Gazelle\Manager\Torrent(), new \Gazelle\Tracker());
    }
}
