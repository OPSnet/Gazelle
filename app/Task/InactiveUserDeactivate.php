<?php

namespace Gazelle\Task;

class InactiveUserDeactivate extends \Gazelle\Task {
    public function run(): void {
        $this->processed = (new \Gazelle\Manager\User)->inactiveUserDeactivate(new \Gazelle\Tracker);
    }
}
