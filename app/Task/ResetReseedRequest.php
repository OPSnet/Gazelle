<?php

namespace Gazelle\Task;

class ResetReseedRequest extends \Gazelle\Task {
    public function run(): void {
        $this->processed += (new \Gazelle\Manager\Torrent)->resetReseededRequest();
    }
}
