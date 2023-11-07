<?php

if (!$Viewer->permitted('site_torrents_notify')) {
    json_die("failure");
}

echo (new Gazelle\Json\Notification\Torrent(
    new Gazelle\User\Notification\Torrent($Viewer),
    new Gazelle\Util\Paginator(TORRENTS_PER_PAGE, (int)($_GET['page'] ?? 1)),
    new Gazelle\Manager\Torrent,
))
    ->setVersion(2)
    ->response();
