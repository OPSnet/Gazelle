<?php

namespace Gazelle\Task;

class LockOldThreads extends \Gazelle\Task {
    public function run(): void {
        $this->processed = (new \Gazelle\Manager\ForumThread())->lockOldThreads();
    }
}
