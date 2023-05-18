<?php

namespace Gazelle\Task;

class SSLCertificate extends \Gazelle\Task {
    public function run(): void {
        $this->processed += (new \Gazelle\Manager\SSLHost)->schedule();
    }
}
