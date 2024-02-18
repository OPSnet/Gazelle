<?php

namespace Gazelle\Task;

class UploadNotifier extends \Gazelle\Task {
    public function run(): void {
        $this->processed = (new \Gazelle\Manager\Notification())
            ->processBacklog(
                new \Gazelle\Manager\NotificationTicket(),
                new \Gazelle\Manager\Torrent(),
            );
    }
}
