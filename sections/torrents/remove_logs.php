<?php
/** @phpstan-var \Gazelle\User $Viewer */

declare(strict_types=1);

namespace Gazelle;

authorize();

if (!$Viewer->permitted('torrents_delete')) {
    Error403::error(
        'You are not allowed to delete torrents. Please report the torrent instead.'
    );
}
$torrent = new Manager\Torrent()->findById((int)($_GET['torrentid'] ?? 0));
if (is_null($torrent)) {
    Error404::error("Cannot find torrent to remove riplogs");
}

new Manager\Riplog()->removeAll($torrent, $Viewer);
header("Location: {$torrent->location()}");
