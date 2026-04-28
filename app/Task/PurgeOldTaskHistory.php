<?php

namespace Gazelle\Task;

class PurgeOldTaskHistory extends \Gazelle\Task {
    public function run(): void {
        $this->processed = $this->pg()->query("
            delete from task_history
            where created < now() - interval '6 month'
        ");
    }
}
