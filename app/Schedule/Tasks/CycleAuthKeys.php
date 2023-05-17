<?php

namespace Gazelle\Schedule\Tasks;

class CycleAuthKeys extends \Gazelle\Schedule\Task {
    public function run(): void {
        $this->processed += (new \Gazelle\Manager\User)->cycleAuthKeys();
    }
}
