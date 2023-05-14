<?php

namespace Gazelle\Schedule\Tasks;

class DisableLeechingRatioWatch extends \Gazelle\Schedule\Task {
    public function run(): void {
        $this->processed += (new \Gazelle\Manager\User)->ratioWatchBlock(new \Gazelle\Tracker, $this);
    }
}
