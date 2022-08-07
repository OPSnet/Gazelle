<?php

namespace Gazelle\Schedule\Tasks;

class PromoteUsers extends \Gazelle\Schedule\Task {
    public function run() {
        $this->processed += (new \Gazelle\Manager\User)->demote(true, $this);
    }
}
