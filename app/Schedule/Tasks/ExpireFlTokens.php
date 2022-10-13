<?php

namespace Gazelle\Schedule\Tasks;

class ExpireFlTokens extends \Gazelle\Schedule\Task {
    public function run() {
        $this->processed = (new \Gazelle\Manager\User)->expireFreeleechTokens(new \Gazelle\Tracker);
    }
}
