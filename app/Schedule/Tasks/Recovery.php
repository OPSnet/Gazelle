<?php

namespace Gazelle\Schedule\Tasks;

class Recovery extends \Gazelle\Schedule\Task {
    public function run(): void {
        if (RECOVERY) {
            $recovery = new \Gazelle\Manager\Recovery;
            if (RECOVERY_AUTOVALIDATE) {
                $recovery->validatePending();
            }
            $recovery->boostUpload();
        }
    }
}
