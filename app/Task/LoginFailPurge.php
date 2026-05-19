<?php

namespace Gazelle\Task;

class LoginFailPurge extends \Gazelle\Task {
    public function run(): void {
        $this->processed = $this->pg()->query("
            delete from history_login_fail
            where created < now() - interval '1 year'
        ");
    }
}
