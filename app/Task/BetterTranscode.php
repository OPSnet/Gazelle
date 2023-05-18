<?php

namespace Gazelle\Task;

class BetterTranscode extends \Gazelle\Task {
    public function run(): void {
        $this->processed = (new \Gazelle\Manager\TGroup)->refreshBetterTranscode();
    }
}
