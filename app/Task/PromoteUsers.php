<?php

namespace Gazelle\Task;

class PromoteUsers extends \Gazelle\Task {
    public function run(): void {
        $manager = new \Gazelle\Manager\User();
        $this->processed += $manager->demote($this);
        $this->processed += $manager->promote($this);
    }
}
