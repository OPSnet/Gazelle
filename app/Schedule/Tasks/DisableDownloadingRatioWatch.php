<?php

namespace Gazelle\Schedule\Tasks;

class DisableDownloadingRatioWatch extends \Gazelle\Schedule\Task {
    public function run() {
        $this->processed += (new \Gazelle\Manager\User)->triggerRatioWatch(new \Gazelle\Tracker, $this);
    }
}
