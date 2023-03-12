<?php

namespace Gazelle\Schedule\Tasks;

class UserLastAccess extends \Gazelle\Schedule\Task {
    public function run(): void {
        $this->processed += (new \Gazelle\Manager\User)->updateLastAccess();
    }
}

