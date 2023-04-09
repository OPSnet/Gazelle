<?php

namespace Gazelle\Schedule\Tasks;

class UploadNotifier extends \Gazelle\Schedule\Task {
    public function run(): void {
        $this->processed = (new \Gazelle\Manager\Notification)
            ->processBacklog(
                new \Gazelle\Manager\NotificationTicket,
                new \Gazelle\Manager\Torrent,
            );
    }
}

