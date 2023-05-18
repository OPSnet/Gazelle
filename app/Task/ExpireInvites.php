<?php

namespace Gazelle\Task;

class ExpireInvites extends \Gazelle\Task {
    public function run(): void {
        $this->processed = (new \Gazelle\Manager\Invite)->expire($this);
    }
}
