<?php

namespace Gazelle\Task;

class UpdateDailyTop10 extends \Gazelle\Task {
    public function run(): void {
        $this->processed = (new \Gazelle\Manager\Torrent)->storeTop10('Daily', 'day', 1);
    }
}
