<?php

namespace Gazelle\Schedule\Tasks;

class RemoveDeadSessions extends \Gazelle\Schedule\Task {
    public function run(): void {
        $this->processed += (new \Gazelle\Manager\Session)->purge();
    }
}
