<?php
/** @phpstan-var \Gazelle\User $Viewer */

declare(strict_types=1);

namespace Gazelle;

if (!$Viewer->permitted('site_torrents_notify')) {
    json_die("failure");
}

echo new Json\Notification\Torrent(
    new User\Notification\Torrent($Viewer),
    new Util\Paginator(TORRENTS_PER_PAGE, (int)($_GET['page'] ?? 1)),
)
    ->setVersion(2)
    ->response();
