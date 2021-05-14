<?php

namespace Gazelle\Schedule\Tasks;

class Recovery extends \Gazelle\Schedule\Task
{
    public function run()
    {
        $recovery = new \Gazelle\Recovery;
        if (defined('RECOVERY_AUTOVALIDATE') && RECOVERY_AUTOVALIDATE) {
            $recovery->validatePending();
        }
        $recovery->boostUpload();
    }
}
