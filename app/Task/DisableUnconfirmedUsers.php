<?php

namespace Gazelle\Task;

class DisableUnconfirmedUsers extends \Gazelle\Task {
    public function run(): void {
        $this->processed = (new \Gazelle\Manager\User())->disableUnconfirmedUsers();
    }
}
