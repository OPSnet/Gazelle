<?php

namespace Gazelle\Task;

class UserLastAccess extends \Gazelle\Task {
    public function run(): void {
        $this->processed += (new \Gazelle\Manager\User())->updateLastAccess();
    }
}
