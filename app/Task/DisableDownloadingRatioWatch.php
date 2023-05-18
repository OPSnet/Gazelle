<?php

namespace Gazelle\Task;

class DisableDownloadingRatioWatch extends \Gazelle\Task {
    public function run(): void {
        $this->processed += (new \Gazelle\Manager\User)->ratioWatchEngage(new \Gazelle\Tracker, $this);
    }
}
