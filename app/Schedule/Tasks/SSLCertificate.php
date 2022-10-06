<?php

namespace Gazelle\Schedule\Tasks;

class SSLCertificate extends \Gazelle\Schedule\Task {
    public function run() {
        $this->processed += (new \Gazelle\Manager\SSLHost)->schedule();
    }
}
