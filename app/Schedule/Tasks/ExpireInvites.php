<?php

namespace Gazelle\Schedule\Tasks;

class ExpireInvites extends \Gazelle\Schedule\Task {
    public function run() {
        $this->processed = (new \Gazelle\Manager\Invite)->expire($this);
    }
}
