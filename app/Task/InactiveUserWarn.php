<?php

namespace Gazelle\Task;

class InactiveUserWarn extends \Gazelle\Task {
    public function run(): void {
        $this->processed = (new \Gazelle\Manager\User())->inactiveUserWarn(new \Gazelle\Util\Mail());
    }
}
