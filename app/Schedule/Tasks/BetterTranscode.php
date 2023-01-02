<?php

namespace Gazelle\Schedule\Tasks;

class BetterTranscode extends \Gazelle\Schedule\Task {
    public function run() {
        $this->processed = (new \Gazelle\Manager\TGroup)->refreshBetterTranscode();
    }
}
