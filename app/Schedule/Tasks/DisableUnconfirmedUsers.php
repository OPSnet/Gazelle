<?php

namespace Gazelle\Schedule\Tasks;

class DisableUnconfirmedUsers extends \Gazelle\Schedule\Task {
    public function run(): void {
        $this->processed = (new \Gazelle\Manager\User)->disableUnconfirmedUsers();
    }
}
