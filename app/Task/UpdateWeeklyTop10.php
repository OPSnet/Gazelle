<?php

namespace Gazelle\Task;

class UpdateWeeklyTop10 extends \Gazelle\Task {
    public function run(): void {
        $this->processed = (new \Gazelle\Manager\Torrent())->storeTop10('Weekly', 'week', 7);
    }
}
