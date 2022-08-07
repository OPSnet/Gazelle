<?php

namespace Gazelle\Schedule\Tasks;

class DemoteUsers extends \Gazelle\Schedule\Task {
    public function run() {
        $this->processed += (new \Gazelle\Manager\User)->promote(true, $this);
    }
}
