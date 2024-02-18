<?php

namespace Gazelle\Task;

class RatioRequirements extends \Gazelle\Task {
    public function run(): void {
        $this->processed = (new \Gazelle\Manager\User())->updateRatioRequirements();
    }
}
