<?php

namespace Gazelle\Schedule\Tasks;

class FrontPageStats extends \Gazelle\Schedule\Task {
    public function run() {
        $this->processed = (new \Gazelle\Stats\Users)->frontPage();
    }
}
