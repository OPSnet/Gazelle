<?php

namespace Gazelle\Task;

class UpdateUserBonusPoints extends \Gazelle\Task {
    public function run(): void {
        $this->processed = (new \Gazelle\Manager\Bonus())->givePoints($this);
    }
}
