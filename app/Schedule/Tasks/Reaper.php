<?php

namespace Gazelle\Schedule\Tasks;

class Reaper extends \Gazelle\Schedule\Task {
    public function run(): void {
        $reaper = new \Gazelle\Torrent\Reaper(new \Gazelle\Manager\Torrent, new \Gazelle\Manager\User);
        $this->processed = 0;
        if (REAPER_TASK_CLAIM) {
            $reaper->claim();
        }
        if (REAPER_TASK_NOTIFY) {
            $this->processed += $reaper->notify();
        }
        if (REAPER_TASK_REMOVE_UNSEEDED) {
            $this->processed += $reaper->removeUnseeded();
        }
        if (REAPER_TASK_REMOVE_NEVER_SEEDED) {
            $this->processed += $reaper->removeNeverSeeded();
        }
    }
}
