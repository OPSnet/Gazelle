<?php

namespace Gazelle\Schedule\Tasks;

class RatioWatch extends \Gazelle\Schedule\Task {
    public function run(): void {
        $this->processed += (new \Gazelle\Manager\User)->ratioWatchAudit(new \Gazelle\Tracker, $this);
    }
}
