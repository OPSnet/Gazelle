<?php

namespace Gazelle\Task;

class CycleAuthKeys extends \Gazelle\Task {
    public function run(): void {
        $this->processed += (new \Gazelle\Manager\User)->cycleAuthKeys();
    }
}
