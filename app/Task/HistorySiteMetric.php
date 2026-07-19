<?php

namespace Gazelle\Task;

use Gazelle\SiteInfo;

class HistorySiteMetric extends \Gazelle\Task {
    public function run(): void {
        $this->processed = new SiteInfo()->refreshHistorySiteMetric();
    }
}
