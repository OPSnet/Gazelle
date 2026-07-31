<?php

namespace Gazelle\Task;

class LowerLoginAttempts extends \Gazelle\Task {
    public function run(): void {
        $this->processed = $this->pg()->query("
            update login_watch set
                attempt = attempt - 1
            where attempt > 0
                and modified < now() - interval '1 hour'
        ");

        $this->processed += $this->pg()->query("
            delete from login_watch
            where modified < now() - interval '90 day'
        ");
    }
}
