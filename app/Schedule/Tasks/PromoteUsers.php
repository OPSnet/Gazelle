<?php

namespace Gazelle\Schedule\Tasks;

class PromoteUsers extends \Gazelle\Schedule\Task {
    public function run() {
        $manager = new \Gazelle\Manager\User;
        $this->processed += $manager->demote($this);
        $this->processed += $manager->promote($this);
    }
}
