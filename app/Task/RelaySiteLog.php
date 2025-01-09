<?php

namespace Gazelle\Task;

use Gazelle\Manager\User as UserMan;
use Gazelle\Manager\SiteLog as SiteLog;

class RelaySiteLog extends \Gazelle\Task {
    public function run(): void {
        $this->processed += (new SiteLog(new UserMan()))->relay();
    }
}
