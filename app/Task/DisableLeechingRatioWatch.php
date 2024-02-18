<?php

namespace Gazelle\Task;

class DisableLeechingRatioWatch extends \Gazelle\Task {
    public function run(): void {
        $this->processed += (new \Gazelle\Manager\User())->ratioWatchBlock(new \Gazelle\Tracker(), $this);
    }
}
