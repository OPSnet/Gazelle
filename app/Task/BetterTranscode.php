<?php

namespace Gazelle\Task;

use Gazelle\Manager\TGroup;

class BetterTranscode extends \Gazelle\Task {
    public function run(): void {
        $this->processed = new TGroup()->refreshBetter();
    }
}
