<?php

namespace Gazelle\Task;

class RatioWatch extends \Gazelle\Task {
    public function run(): void {
        $this->processed += (new \Gazelle\Manager\User)->ratioWatchAudit(new \Gazelle\Tracker, $this);
    }
}
