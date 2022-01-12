<?php

if (!$Viewer->permitted('site_torrents_notify')) {
    json_die("failure");
}

(new Gazelle\Json\Notification\Torrent)
    ->setNotifier(new Gazelle\User\Notification\Torrent($Viewer))
    ->setPaginator(
        new Gazelle\Util\Paginator(TORRENTS_PER_PAGE, (int)($_GET['page'] ?? 1))
    )
    ->setTorrentManager(new Gazelle\Manager\Torrent)
    ->setVersion(2)
    ->emit();
