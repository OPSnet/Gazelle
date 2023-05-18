<?php

namespace Gazelle\Task;

class Recovery extends \Gazelle\Task {
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
