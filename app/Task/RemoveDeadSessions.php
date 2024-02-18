<?php

namespace Gazelle\Task;

class RemoveDeadSessions extends \Gazelle\Task {
    public function run(): void {
        $this->processed += (new \Gazelle\Manager\Session())->purge();
    }
}
