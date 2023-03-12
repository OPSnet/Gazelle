<?php

namespace Gazelle\Schedule\Tasks;

class LockOldThreads extends \Gazelle\Schedule\Task {
    public function run(): void {
        $this->processed = (new \Gazelle\Manager\ForumThread)->lockOldThreads();
    }
}
